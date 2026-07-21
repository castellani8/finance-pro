<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PortfolioSnapshot;
use App\Models\Tenant;
use App\Support\CompanyFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Séries da carteira (valor investido x valor de mercado), no estilo "evolução
 * do patrimônio" de plataformas como o Investidor 10, com benchmarks de
 * comparação: os mesmos aportes rendendo 100% do CDI e acompanhando o IBOV.
 */
class PortfolioEvolution
{
    /**
     * Série mensal: um ponto no fim de cada mês e um ponto final em "hoje".
     *
     * @return array{labels: array<int, string>, invested: array<int, float>, current: array<int, float>, cdi: array<int, float>, ibov: array<int, float>}
     */
    public function monthlySeries(Tenant $tenant, ?int $months = null, int|string|null $companyId = null): array
    {
        $assets = $this->loadAssets($tenant, $companyId);
        $firstDate = $this->firstTransactionDate($assets);

        if ($firstDate === null) {
            return $this->emptySeries();
        }

        return $this->buildSeries($assets, $this->monthlySnapshotDates($firstDate, $months), 'M/y');
    }

    /**
     * Série diária dos últimos $days dias, materializada em portfolio_snapshots:
     * dias já fotografados são lidos da tabela; os que faltam são calculados e
     * gravados, então a primeira visualização "preenche" o histórico.
     *
     * @return array{labels: array<int, string>, invested: array<int, float>, current: array<int, float>, cdi: array<int, float>, ibov: array<int, float>}
     */
    public function dailySeries(Tenant $tenant, int $days = 30, bool $rebuild = false, int|string|null $companyId = null): array
    {
        $assets = $this->loadAssets($tenant, $companyId);
        $firstDate = $this->firstTransactionDate($assets);

        if ($firstDate === null) {
            return $this->emptySeries();
        }

        // Snapshots materializados valem para a carteira INTEIRA do tenant;
        // com filtro de empresa, calcula direto por data (sem ler/gravar).
        if ($companyId !== null) {
            $start = max($firstDate, now()->subDays($days - 1)->toDateString());
            $dates = [];
            $cursor = Carbon::parse($start);

            while ($cursor->lessThanOrEqualTo(now()->startOfDay())) {
                $dates[] = $cursor->toDateString();
                $cursor = $cursor->addDay();
            }

            return $this->buildSeries($assets, $dates, 'd/m');
        }

        $start = max($firstDate, now()->subDays($days - 1)->toDateString());
        $dates = [];
        $cursor = Carbon::parse($start);
        $today = now()->startOfDay();

        while ($cursor->lessThanOrEqualTo($today)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        $stored = $rebuild
            ? collect()
            : PortfolioSnapshot::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('date', $dates)
                ->get()
                ->keyBy(fn (PortfolioSnapshot $s): string => substr((string) $s->getRawOriginal('date'), 0, 10));

        // "Hoje" sempre recalcula: cotações e importações mudam ao longo do dia.
        $todayKey = $today->toDateString();
        $missing = array_values(array_filter(
            $dates,
            fn (string $d): bool => $d === $todayKey || ! $stored->has($d),
        ));

        $computed = [];

        if ($missing !== []) {
            $prices = $this->loadPriceSeries($assets);

            foreach ($missing as $date) {
                $computed[$date] = $this->valuePortfolioAt($assets, $prices, $date);
            }

            $this->persistSnapshots($tenant, $computed);
        }

        $invested = [];
        $current = [];
        $labels = [];

        foreach ($dates as $date) {
            [$inv, $cur] = $computed[$date] ?? [
                (float) $stored[$date]->invested,
                (float) $stored[$date]->current_value,
            ];

            $labels[] = Carbon::parse($date)->format('d/m');
            $invested[] = round($inv, 2);
            $current[] = round($cur, 2);
        }

        $hasInvestments = $assets->contains(fn (Asset $asset): bool => ! $asset->isPhysical());

        return [
            'labels' => $labels,
            'invested' => $invested,
            'current' => $current,
            'cdi' => $hasInvestments ? $this->benchmarkSeries($dates, $invested, $this->cdiFactorFn()) : [],
            'ibov' => $hasInvestments ? $this->benchmarkSeries($dates, $invested, $this->ibovFactorFn()) : [],
        ];
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @param  array<int, string>  $dates
     * @return array{labels: array<int, string>, invested: array<int, float>, current: array<int, float>, cdi: array<int, float>, ibov: array<int, float>}
     */
    private function buildSeries(Collection $assets, array $dates, string $labelFormat): array
    {
        $prices = $this->loadPriceSeries($assets);

        $labels = [];
        $invested = [];
        $current = [];

        foreach ($dates as $date) {
            [$inv, $cur] = $this->valuePortfolioAt($assets, $prices, $date);

            $labels[] = Carbon::parse($date)->locale('pt_BR')->translatedFormat($labelFormat);
            $invested[] = round($inv, 2);
            $current[] = round($cur, 2);
        }

        // Comparar com CDI/IBOV só faz sentido quando o recorte tem ativos de
        // investimento — pra um conjunto só de bens físicos (ex: uma empresa
        // que tem apenas um carro), o benchmark vira ruído.
        $hasInvestments = $assets->contains(fn (Asset $asset): bool => ! $asset->isPhysical());

        return [
            'labels' => $labels,
            'invested' => $invested,
            'current' => $current,
            'cdi' => $hasInvestments ? $this->benchmarkSeries($dates, $invested, $this->cdiFactorFn()) : [],
            'ibov' => $hasInvestments ? $this->benchmarkSeries($dates, $invested, $this->ibovFactorFn()) : [],
        ];
    }

    /**
     * Valor investido e de mercado da carteira inteira numa data.
     *
     * @param  Collection<int, Asset>  $assets
     * @param  array<string, array{dates: array<int, string>, closes: array<int, float>}>  $prices
     * @return array{0: float, 1: float}
     */
    private function valuePortfolioAt(Collection $assets, array $prices, string $date): array
    {
        $invested = 0.0;
        $current = 0.0;

        foreach ($assets as $asset) {
            $price = $asset->type !== 'FIXED_INCOME'
                ? $this->priceAt($prices, $asset->ticker_or_code, $date)
                : null;

            $invested += max(0.0, $asset->purchaseValue($date));
            $current += max(0.0, $asset->valueAt($date, $price));
        }

        return [$invested, $current];
    }

    /**
     * Simula os mesmos aportes rendendo pelo benchmark: a cada intervalo o
     * saldo é corrigido pelo fator do período e recebe o delta de aportes.
     *
     * @param  array<int, string>  $dates
     * @param  array<int, float>  $invested
     * @param  callable(string, string): float  $factorBetween
     * @return array<int, float>
     */
    private function benchmarkSeries(array $dates, array $invested, callable $factorBetween): array
    {
        $series = [];
        $balance = 0.0;
        $previousDate = null;
        $previousInvested = 0.0;

        foreach ($dates as $i => $date) {
            if ($previousDate !== null) {
                $balance *= $factorBetween($previousDate, $date);
            }

            $balance += $invested[$i] - $previousInvested;
            $balance = max(0.0, $balance);

            $series[] = round($balance, 2);
            $previousDate = $date;
            $previousInvested = $invested[$i];
        }

        return $series;
    }

    /** @return callable(string, string): float */
    private function cdiFactorFn(): callable
    {
        $accumulator = app(IndexAccumulator::class);

        return fn (string $from, string $to): float => $accumulator->factorBetween('CDI', $from, $to);
    }

    /** @return callable(string, string): float */
    private function ibovFactorFn(): callable
    {
        $ibov = $this->loadTickerSeries('^BVSP');

        return function (string $from, string $to) use ($ibov): float {
            $base = $this->priceAt($ibov, '^BVSP', $from);
            $target = $this->priceAt($ibov, '^BVSP', $to);

            return ($base !== null && $target !== null && $base > 0) ? $target / $base : 1.0;
        };
    }

    /** @param  array<string, array{0: float, 1: float}>  $computed */
    private function persistSnapshots(Tenant $tenant, array $computed): void
    {
        $rows = [];

        foreach ($computed as $date => [$invested, $current]) {
            $rows[] = [
                'tenant_id' => $tenant->getKey(),
                'date' => $date,
                'invested' => round($invested, 2),
                'current_value' => round($current, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        PortfolioSnapshot::upsert($rows, ['tenant_id', 'date'], ['invested', 'current_value', 'updated_at']);
    }

    /** @return Collection<int, Asset> */
    private function loadAssets(Tenant $tenant, int|string|null $companyId = null): Collection
    {
        return CompanyFilter::applyToCompanyColumn(
            Asset::query()->where('tenant_id', $tenant->getKey()),
            $companyId,
        )
            ->with('transactions')
            ->get();
    }

    /** @param  Collection<int, Asset>  $assets */
    private function firstTransactionDate(Collection $assets): ?string
    {
        $first = $assets
            ->flatMap(fn (Asset $asset) => $asset->transactions)
            ->map(fn ($t) => substr((string) $t->getRawOriginal('transaction_date'), 0, 10))
            ->filter()
            ->min();

        return $first ?: null;
    }

    /** @return array{labels: array<int, string>, invested: array<int, float>, current: array<int, float>, cdi: array<int, float>, ibov: array<int, float>} */
    private function emptySeries(): array
    {
        return ['labels' => [], 'invested' => [], 'current' => [], 'cdi' => [], 'ibov' => []];
    }

    /**
     * Fim de cada mês entre a primeira movimentação (ou o recorte de $months)
     * e hoje, mais o ponto de "hoje".
     *
     * @return array<int, string>
     */
    private function monthlySnapshotDates(string $firstDate, ?int $months): array
    {
        $today = now()->startOfDay();
        $cursor = Carbon::parse($firstDate)->endOfMonth()->startOfDay();

        if ($months !== null) {
            $limit = $today->copy()->subMonthsNoOverflow($months)->endOfMonth()->startOfDay();

            if ($limit->greaterThan($cursor)) {
                $cursor = $limit;
            }
        }

        $dates = [];

        while ($cursor->lessThan($today)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay()->endOfMonth()->startOfDay();
        }

        $dates[] = $today->toDateString();

        return $dates;
    }

    /**
     * Histórico de fechamentos por ticker para os ativos de renda variável.
     *
     * @param  Collection<int, Asset>  $assets
     * @return array<string, array{dates: array<int, string>, closes: array<int, float>}>
     */
    private function loadPriceSeries(Collection $assets): array
    {
        $tickers = $assets
            ->where('type', '!=', 'FIXED_INCOME')
            ->pluck('ticker_or_code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->loadTickerSeries(...$tickers);
    }

    /**
     * Query base (sem hidratar models — são centenas de milhares de linhas)
     * ordenada por (ticker, date) para casar com o índice composto.
     *
     * @return array<string, array{dates: array<int, string>, closes: array<int, float>}>
     */
    private function loadTickerSeries(string ...$tickers): array
    {
        if ($tickers === []) {
            return [];
        }

        $rows = DB::table('asset_price_history')
            ->whereIn('ticker', $tickers)
            ->whereNotNull('price_close')
            ->orderBy('ticker')
            ->orderBy('date')
            ->get(['ticker', 'date', 'price_close']);

        $series = [];

        foreach ($rows as $row) {
            $series[$row->ticker]['dates'][] = (string) $row->date;
            $series[$row->ticker]['closes'][] = (float) $row->price_close;
        }

        return $series;
    }

    /**
     * Último fechamento conhecido do ticker até a data (null se não houver).
     *
     * @param  array<string, array{dates: array<int, string>, closes: array<int, float>}>  $prices
     */
    private function priceAt(array $prices, ?string $ticker, string $date): ?float
    {
        if ($ticker === null || ! isset($prices[$ticker])) {
            return null;
        }

        $dates = $prices[$ticker]['dates'];
        $closes = $prices[$ticker]['closes'];

        $lo = 0;
        $hi = count($dates) - 1;
        $pos = -1;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);

            if ($dates[$mid] <= $date) {
                $pos = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $pos >= 0 ? $closes[$pos] : null;
    }
}
