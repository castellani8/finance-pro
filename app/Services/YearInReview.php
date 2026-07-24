<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Retrospectiva anual: os números do ano prontos para o card compartilhável.
 * Tudo vem dos dados reais do tenant — nada de estimativa aqui.
 */
class YearInReview
{
    public function __construct(private PortfolioEvolution $evolution) {}

    /**
     * @return array{
     *     ano: int, renda_passiva_total: float, melhor_mes: ?array{label: string, valor: float},
     *     top_ativos: array<int, array{nome: string, valor: float}>, aportes: float,
     *     movimentacoes: int, num_ativos: int, patrimonio_inicio: ?float, patrimonio_fim: ?float,
     *     variacao_pct: ?float
     * }
     */
    public function build(Tenant $tenant, int $year): array
    {
        $incomes = Transaction::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('type', Asset::CASH_INCOME_TYPES)
            ->whereNotNull('asset_id')
            ->whereBetween('transaction_date', ["{$year}-01-01", "{$year}-12-31"])
            ->with('asset')
            ->get();

        $converter = app(CurrencyConverter::class);

        $signedBrl = function (Transaction $t) use ($converter): float {
            $date = substr((string) $t->getRawOriginal('transaction_date'), 0, 10);
            $currency = $t->asset?->currency ?? 'BRL';

            return $t->flowDirection()->sign() * $converter->toBrl((float) $t->total_amount, $currency, $date);
        };

        // Renda passiva por mês do ano.
        $porMes = array_fill(1, 12, 0.0);

        foreach ($incomes as $t) {
            $mes = (int) substr((string) $t->getRawOriginal('transaction_date'), 5, 2);
            $porMes[$mes] += $signedBrl($t);
        }

        $melhorMes = null;

        if ($porMes !== array_fill(1, 12, 0.0)) {
            $mes = array_keys($porMes, max($porMes))[0];
            $melhorMes = [
                'label' => Carbon::create($year, $mes, 1)->locale('pt_BR')->translatedFormat('F'),
                'valor' => round($porMes[$mes], 2),
            ];
        }

        // Top ativos pagadores do ano.
        $topAtivos = $incomes
            ->groupBy('asset_id')
            ->map(fn ($group) => [
                'nome' => $group->first()->asset->name,
                'valor' => round($group->sum($signedBrl), 2),
            ])
            ->sortByDesc('valor')
            ->take(3)
            ->values()
            ->all();

        // Aportes: total comprado no ano (em BRL pela data de cada compra).
        $aportes = Transaction::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', 'BUY')
            ->whereBetween('transaction_date', ["{$year}-01-01", "{$year}-12-31"])
            ->with('asset')
            ->get()
            ->sum(function (Transaction $t) use ($converter): float {
                $date = substr((string) $t->getRawOriginal('transaction_date'), 0, 10);

                return $converter->toBrl((float) $t->total_amount, $t->asset?->currency ?? 'BRL', $date);
            });

        [$inicio, $fim] = $this->netWorthBounds($tenant, $year);

        return [
            'ano' => $year,
            'renda_passiva_total' => round(array_sum($porMes), 2),
            'melhor_mes' => $melhorMes,
            'top_ativos' => $topAtivos,
            'aportes' => round($aportes, 2),
            'movimentacoes' => Transaction::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereBetween('transaction_date', ["{$year}-01-01", "{$year}-12-31"])
                ->count(),
            'num_ativos' => Asset::query()
                ->where('tenant_id', $tenant->getKey())
                ->wherePositionPositive()
                ->count(),
            'patrimonio_inicio' => $inicio,
            'patrimonio_fim' => $fim,
            'variacao_pct' => ($inicio !== null && $inicio > 0 && $fim !== null)
                ? round(($fim / $inicio - 1) * 100, 1)
                : null,
        ];
    }

    /**
     * Patrimônio no início e no fim do ano a partir da série mensal de
     * evolução (mesmos labels 'M/y' do gráfico do dashboard).
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function netWorthBounds(Tenant $tenant, int $year): array
    {
        $series = $this->evolution->monthlySeries($tenant);
        $labels = $series['labels'];
        $current = $series['current'];

        $labelFor = fn (int $y, int $m): string => Carbon::create($y, $m, 1)
            ->locale('pt_BR')
            ->translatedFormat('M/y');

        $indexOf = fn (string $label): ?int => ($i = array_search($label, $labels, true)) === false ? null : $i;

        // Início: dez do ano anterior; sem esse ponto, a carteira nasceu no ano.
        $inicioIdx = $indexOf($labelFor($year - 1, 12));
        $inicio = $inicioIdx !== null ? (float) $current[$inicioIdx] : null;

        // Fim: dez do ano; para o ano corrente vale o último ponto ("hoje").
        $fimIdx = $indexOf($labelFor($year, 12));

        if ($fimIdx === null && $year === now()->year && $current !== []) {
            $fimIdx = count($current) - 1;
        }

        $fim = $fimIdx !== null ? (float) $current[$fimIdx] : null;

        return [$inicio, $fim];
    }
}
