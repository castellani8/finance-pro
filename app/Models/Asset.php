<?php

namespace App\Models;

use App\Services\IndexAccumulator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Asset extends Model
{
    use HasFactory;

    protected $table = 'assets';

    protected $fillable = ['tenant_id', 'company_id', 'name', 'type', 'ticker_or_code', 'metadata', 'currency'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Cache do último preço por ticker durante a request (evita N+1). */
    protected static array $priceCache = [];

    /** Cache dos dois últimos fechamentos por ticker (variação do dia). */
    protected static array $lastClosesCache = [];

    /**
     * Tipos de transação que movem custódia (alteram a quantidade em carteira).
     * Proventos (DIVIDEND, JCP, INCOME, INTEREST, FRACTION_AUCTION), bloqueios
     * (CUSTODY_BLOCK) e atualizações de posição da B3 (UPDATE) ficam de fora.
     */
    public const POSITION_TYPES = [
        'BUY', 'SELL', 'BONUS', 'SPLIT', 'SUBSCRIPTION', 'RIGHTS_CESSION', 'TRANSFER', 'GROUPING',
    ];

    /** Tipos que representam dinheiro creditado ao investidor (proventos e afins). */
    public const CASH_INCOME_TYPES = [
        'DIVIDEND', 'JCP', 'INCOME', 'INTEREST', 'AMORTIZATION', 'FRACTION_AUCTION',
    ];

    /**
     * Classes de ativo físico/manual: sem cotação de mercado, o valor vem dos
     * lançamentos do usuário (aquisição, benfeitorias, reavaliações) menos a
     * depreciação linear configurada.
     */
    public const PHYSICAL_TYPES = [
        'VEHICLE', 'MACHINERY', 'REAL_ESTATE', 'COMMODITY', 'COLLECTIBLE', 'SOFTWARE', 'OTHER',
    ];

    /** Rótulos em português por tipo, usados em formulários, tabelas e gráficos. */
    public const TYPE_LABELS = [
        'STOCK' => 'Ação',
        'FII' => 'Fundo Imobiliário',
        'FIXED_INCOME' => 'Renda Fixa',
        'OPTION' => 'Opção',
        'VEHICLE' => 'Veículo',
        'MACHINERY' => 'Máquina / Equipamento',
        'REAL_ESTATE' => 'Imóvel',
        'COMMODITY' => 'Ouro / Commodity',
        'COLLECTIBLE' => 'Colecionável / Artefato',
        'SOFTWARE' => 'Software',
        'OTHER' => 'Outro',
    ];

    /** Taxa de depreciação anual sugerida por tipo (padrões da Receita). */
    public const DEFAULT_DEPRECIATION_RATES = [
        'VEHICLE' => 20.0,
        'MACHINERY' => 10.0,
        'REAL_ESTATE' => 4.0,
    ];

    public function isPhysical(): bool
    {
        return in_array($this->type, self::PHYSICAL_TYPES, true);
    }

    /** Taxa de depreciação linear anual (% a.a.) configurada no ativo. */
    public function depreciationRate(): float
    {
        $value = $this->metadata['depreciation_rate'] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /** Memoização das transações em ordem cronológica (cálculos repetem por snapshot). */
    protected ?Collection $chronologicalTransactions = null;

    /** Transações ordenadas por data e id, sem cast de Carbon (barato de repetir). */
    protected function chronologicalTransactions(): Collection
    {
        return $this->chronologicalTransactions ??= $this->transactions
            ->sortBy(fn (Transaction $t): string => sprintf(
                '%s|%012d',
                (string) $t->getRawOriginal('transaction_date'),
                (int) $t->id,
            ))
            ->values();
    }

    /**
     * Quantidade líquida em carteira, na ordem cronológica dos eventos:
     * créditos somam, débitos subtraem e grupamento (GROUPING) redefine a
     * posição para o novo total creditado pela B3.
     *
     * @param  string|null  $until  Considera só eventos até esta data (Y-m-d); null = todos.
     */
    public function positionQuantity(?string $until = null): float
    {
        $position = 0.0;

        foreach ($this->chronologicalTransactions() as $t) {
            // O raw pode vir com hora ("2026-07-21 00:00:00"); compara só a data.
            if ($until !== null && substr((string) $t->getRawOriginal('transaction_date'), 0, 10) > $until) {
                break;
            }

            if (! in_array($t->type, self::POSITION_TYPES, true)) {
                continue;
            }

            $quantity = (float) $t->quantity;

            if ($t->type === 'GROUPING') {
                if ($t->isCredit()) {
                    $position = $quantity;
                }

                continue;
            }

            $position += $t->isCredit() ? $quantity : -$quantity;
        }

        return $position;
    }

    /**
     * Preço médio de aquisição: custo das compras diluído pelas ações que
     * entraram de graça (bonificações e desdobramentos entram a custo zero).
     */
    public function averageBuyPrice(?string $until = null): float
    {
        $transactions = $this->transactionsUntil($until);
        $buys = $transactions->where('type', 'BUY');
        $buyQuantity = (float) $buys->sum(fn (Transaction $t) => (float) $t->quantity);

        $zeroCostQuantity = (float) $transactions
            ->filter(fn (Transaction $t): bool => in_array($t->type, ['BONUS', 'SPLIT'], true))
            ->sum(fn (Transaction $t) => $t->isCredit() ? (float) $t->quantity : -(float) $t->quantity);

        $quantity = $buyQuantity + max(0.0, $zeroCostQuantity);

        if ($quantity <= 0) {
            return 0.0;
        }

        return (float) $buys->sum(fn (Transaction $t) => (float) $t->total_amount) / $quantity;
    }

    /**
     * Valor investido: custo da posição atual.
     * - Papéis: preço médio x quantidade atual.
     * - Físicos: aquisições + benfeitorias + despesas (custo real de posse),
     *   proporcional à fração ainda mantida.
     */
    public function purchaseValue(?string $until = null): float
    {
        if ($this->isPhysical()) {
            return $this->physicalInvestedAt($until);
        }

        return $this->averageBuyPrice($until) * $this->positionQuantity($until);
    }

    /**
     * Valor de compra: só o que foi pago na AQUISIÇÃO, sem benfeitorias nem
     * despesas (o preço do Fox é 43 mil; a revisão de 4 mil vai pro custo
     * investido, não pra cá). Para papéis, equivale ao custo da posição.
     */
    public function acquisitionValue(?string $until = null): float
    {
        if (! $this->isPhysical()) {
            return $this->purchaseValue($until);
        }

        $buys = (float) $this->transactionsUntil($until)
            ->where('type', 'BUY')
            ->sum(fn (Transaction $t) => (float) $t->total_amount);

        return max(0.0, $buys * $this->heldFractionAt($until));
    }

    /** Custo total de posse de um ativo físico até a data (null = hoje). */
    protected function physicalInvestedAt(?string $until = null): float
    {
        $transactions = $this->transactionsUntil($until);

        $cost = (float) $transactions
            ->whereIn('type', ['BUY', 'IMPROVEMENT', 'EXPENSE'])
            ->sum(fn (Transaction $t) => (float) $t->total_amount);

        return max(0.0, $cost * $this->heldFractionAt($until));
    }

    /**
     * Fração da posição ainda em carteira (1.0 quando nada foi comprado com
     * quantidade — ex: software construído só com benfeitorias).
     */
    protected function heldFractionAt(?string $until = null): float
    {
        $boughtQuantity = (float) $this->transactionsUntil($until)
            ->where('type', 'BUY')
            ->sum(fn (Transaction $t) => (float) $t->quantity);

        if ($boughtQuantity <= 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, $this->positionQuantity($until) / $boughtQuantity));
    }

    /** Transações até a data-limite (inclusive); sem limite, todas. */
    protected function transactionsUntil(?string $until)
    {
        if ($until === null) {
            return $this->transactions;
        }

        return $this->transactions->filter(
            fn (Transaction $t): bool => substr((string) $t->getRawOriginal('transaction_date'), 0, 10) <= $until
        );
    }

    /** Instituição/corretora da movimentação mais recente do ativo. */
    public function currentInstitution(): ?string
    {
        return $this->transactions
            ->filter(fn (Transaction $t): bool => filled($t->institution))
            ->sortBy([['transaction_date', 'desc'], ['id', 'desc']])
            ->first()
            ?->institution;
    }

    /** Último preço de fechamento conhecido no histórico (por ticker). */
    public function currentUnitPrice(): ?float
    {
        $ticker = $this->ticker_or_code;

        if (! $ticker) {
            return null;
        }

        if (! array_key_exists($ticker, static::$priceCache)) {
            static::$priceCache[$ticker] = AssetPriceHistory::query()
                ->where('ticker', $ticker)
                ->orderByDesc('date')
                ->value('price_close');
        }

        return static::$priceCache[$ticker] !== null
            ? (float) static::$priceCache[$ticker]
            : null;
    }

    /**
     * Variação percentual do último fechamento sobre o anterior (var. do dia).
     * Null quando não há duas cotações no histórico.
     */
    public function dailyChangePercent(): ?float
    {
        $ticker = $this->ticker_or_code;

        if (! $ticker) {
            return null;
        }

        if (! array_key_exists($ticker, static::$lastClosesCache)) {
            static::$lastClosesCache[$ticker] = AssetPriceHistory::query()
                ->where('ticker', $ticker)
                ->whereNotNull('price_close')
                ->orderByDesc('date')
                ->limit(2)
                ->pluck('price_close')
                ->map(fn ($p): float => (float) $p)
                ->all();
        }

        $closes = static::$lastClosesCache[$ticker];

        if (count($closes) < 2 || $closes[1] <= 0) {
            return null;
        }

        return ($closes[0] - $closes[1]) / $closes[1] * 100;
    }

    /** Proventos em dinheiro recebidos nos últimos 12 meses (estornos subtraem). */
    public function incomeLastTwelveMonths(): float
    {
        $cutoff = now()->subMonthsNoOverflow(12)->toDateString();

        return (float) $this->transactions
            ->filter(fn (Transaction $t): bool => in_array($t->type, self::CASH_INCOME_TYPES, true)
                && substr((string) $t->getRawOriginal('transaction_date'), 0, 10) >= $cutoff)
            ->sum(fn (Transaction $t) => ($t->isCredit() ? 1 : -1) * (float) $t->total_amount);
    }

    /** Total de despesas lançadas no ativo (manutenção, custos de posse). */
    public function expensesTotal(): float
    {
        return (float) $this->transactions
            ->where('type', 'EXPENSE')
            ->sum(fn (Transaction $t) => (float) $t->total_amount);
    }

    /** Despesas do ativo nos últimos 12 meses. */
    public function expensesLastTwelveMonths(): float
    {
        $cutoff = now()->subMonthsNoOverflow(12)->toDateString();

        return (float) $this->transactions
            ->filter(fn (Transaction $t): bool => $t->type === 'EXPENSE'
                && substr((string) $t->getRawOriginal('transaction_date'), 0, 10) >= $cutoff)
            ->sum(fn (Transaction $t) => (float) $t->total_amount);
    }

    /**
     * Resultado operacional do ativo: tudo que ele rendeu menos tudo que ele
     * custou de despesas. Positivo = gera renda; negativo = está custando.
     */
    public function netResult(): float
    {
        return $this->dividendsReceived() - $this->expensesTotal();
    }

    /** Resultado operacional dos últimos 12 meses. */
    public function netResultLastTwelveMonths(): float
    {
        return $this->incomeLastTwelveMonths() - $this->expensesLastTwelveMonths();
    }

    /** Dividend Yield 12m: proventos do período sobre o valor atual da posição. */
    public function dividendYield(): ?float
    {
        $current = $this->currentValue();

        if ($current <= 0) {
            return null;
        }

        return $this->incomeLastTwelveMonths() / $current * 100;
    }

    /** Yield on Cost 12m: proventos do período sobre o custo da posição. */
    public function yieldOnCost(): ?float
    {
        $purchase = $this->purchaseValue();

        if ($purchase <= 0) {
            return null;
        }

        return $this->incomeLastTwelveMonths() / $purchase * 100;
    }

    /**
     * Valor atual da posição.
     * - Renda fixa: valor de compra corrigido pelo índice (CDI/IPCA/SELIC).
     * - Físicos: aquisições/benfeitorias ou última reavaliação, menos depreciação.
     * - Demais: quantidade x último preço do histórico; sem cotação, cai para o custo.
     */
    public function currentValue(): float
    {
        if ($this->type === 'FIXED_INCOME') {
            return $this->fixedIncomeCurrentValue();
        }

        if ($this->isPhysical()) {
            return $this->physicalValueAt();
        }

        $price = $this->currentUnitPrice();

        if ($price === null) {
            return $this->purchaseValue();
        }

        return $this->positionQuantity() * $price;
    }

    /**
     * Valor de mercado de um ativo físico na data (null = hoje):
     * a base é a última reavaliação (mais benfeitorias posteriores) ou, sem
     * reavaliação, aquisições + benfeitorias; sobre a base incide a
     * depreciação linear anual desde a aquisição (ou desde a reavaliação,
     * que zera o relógio), proporcional à fração ainda mantida.
     */
    protected function physicalValueAt(?string $until = null): float
    {
        $until ??= now()->toDateString();
        $fraction = $this->heldFractionAt($until);

        if ($fraction <= 0) {
            return 0.0;
        }

        $transactions = $this->transactionsUntil($until);

        $sortKey = fn (Transaction $t): string => sprintf(
            '%s|%012d',
            substr((string) $t->getRawOriginal('transaction_date'), 0, 10),
            (int) $t->id,
        );

        $revaluation = $transactions
            ->where('type', 'REVALUATION')
            ->sortBy($sortKey)
            ->last();

        if ($revaluation !== null) {
            $revaluationKey = $sortKey($revaluation);
            $base = (float) $revaluation->total_amount;
            // Benfeitorias lançadas depois da reavaliação (desempate por id
            // quando caem no mesmo dia) somam à nova base.
            $base += (float) $transactions
                ->where('type', 'IMPROVEMENT')
                ->filter(fn (Transaction $t): bool => $sortKey($t) > $revaluationKey)
                ->sum(fn (Transaction $t) => (float) $t->total_amount);
            $depreciationStart = substr((string) $revaluation->getRawOriginal('transaction_date'), 0, 10);
        } else {
            $base = (float) $transactions
                ->whereIn('type', ['BUY', 'IMPROVEMENT'])
                ->sum(fn (Transaction $t) => (float) $t->total_amount);
            $firstBuy = $transactions
                ->where('type', 'BUY')
                ->map(fn (Transaction $t): string => substr((string) $t->getRawOriginal('transaction_date'), 0, 10))
                ->min();
            $depreciationStart = $firstBuy ?: null;
        }

        $rate = $this->depreciationRate();

        if ($rate > 0 && $depreciationStart !== null) {
            $years = max(0.0, Carbon::parse($depreciationStart)->floatDiffInYears(Carbon::parse($until)));
            $base *= max(0.0, 1 - ($rate / 100) * $years);
        }

        return max(0.0, $base * $fraction);
    }

    /**
     * Corrige cada compra pelo fator acumulado do indexador desde a data do aporte
     * até $until (hoje, se null), proporcional à fração ainda mantida em carteira.
     */
    protected function fixedIncomeCurrentValue(?string $until = null): float
    {
        $until ??= now()->toDateString();
        $transactions = $this->transactionsUntil($until);
        $buys = $transactions->where('type', 'BUY');
        $boughtQuantity = (float) $buys->sum(fn (Transaction $t) => (float) $t->quantity);

        if ($boughtQuantity <= 0) {
            return $this->purchaseValue($until);
        }

        $heldFraction = max(0.0, min(1.0, $this->positionQuantity($until) / $boughtQuantity));

        if ($heldFraction <= 0) {
            return 0.0;
        }

        $indexer = $this->indexer() ?? 'CDI';
        $percent = $this->indexPercent();
        $spread = $this->spread();
        $accumulator = app(IndexAccumulator::class);

        $corrected = $buys->reduce(function (float $carry, Transaction $t) use ($accumulator, $indexer, $percent, $spread, $until): float {
            $from = substr((string) $t->getRawOriginal('transaction_date'), 0, 10);
            $factor = $from !== '' ? $accumulator->factorBetween($indexer, $from, $until, $percent, $spread) : 1.0;

            return $carry + ((float) $t->total_amount * $factor);
        }, 0.0);

        return $corrected * $heldFraction;
    }

    /**
     * Valor da posição na data informada (Y-m-d), usado na série de evolução:
     * renda fixa é corrigida pelo indexador até a data; renda variável usa o
     * preço informado (última cotação conhecida na data) ou cai para o custo.
     */
    public function valueAt(string $date, ?float $price): float
    {
        if ($this->type === 'FIXED_INCOME') {
            return $this->fixedIncomeCurrentValue($date);
        }

        if ($this->isPhysical()) {
            return $this->physicalValueAt($date);
        }

        $quantity = $this->positionQuantity($date);

        if ($quantity <= 0) {
            return 0.0;
        }

        return $price !== null ? $quantity * $price : $this->purchaseValue($date);
    }

    /** Indexador do título de renda fixa (do metadata ou inferido pelo tipo de papel). */
    public function indexer(): ?string
    {
        if ($this->type !== 'FIXED_INCOME') {
            return null;
        }

        $stored = $this->metadata['indexer'] ?? null;

        if (is_string($stored) && $stored !== '') {
            return strtoupper($stored);
        }

        return static::inferIndexer($this->name);
    }

    /** Percentual do índice contratado (ex: 110 para "110% do CDI"). Padrão 100. */
    public function indexPercent(): float
    {
        $value = $this->metadata['index_percent'] ?? null;

        return is_numeric($value) ? (float) $value : 100.0;
    }

    /** Spread anual sobre o índice, ou taxa do prefixado (ex: 4 para "CDI + 4%"). Padrão 0. */
    public function spread(): float
    {
        $value = $this->metadata['spread'] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /** Rótulo legível da rentabilidade contratada (ex: "110% CDI", "IPCA + 5%", "Prefixado 12% a.a."). */
    public function rateLabel(): ?string
    {
        if ($this->type !== 'FIXED_INCOME') {
            return null;
        }

        $indexer = $this->indexer() ?? 'CDI';
        $percent = $this->indexPercent();
        $spread = $this->spread();

        $fmt = fn (float $n): string => rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');

        if ($indexer === 'PREFIXADO') {
            return 'Prefixado '.$fmt($spread).'% a.a.';
        }

        $label = $percent != 100.0 ? $fmt($percent).'% '.$indexer : $indexer;

        if ($percent == 100.0 && $spread == 0.0) {
            $label = '100% '.$indexer;
        }

        if ($spread != 0.0) {
            $label .= ' + '.$fmt($spread).'%';
        }

        return $label;
    }

    /** Heurística de indexador a partir da descrição do papel (CRI/CRA/DEB ~ IPCA, resto ~ CDI). */
    public static function inferIndexer(?string $name): string
    {
        $normalized = Str::upper(Str::ascii((string) $name));

        return match (true) {
            Str::startsWith($normalized, ['CRI', 'CRA', 'DEB']) => 'IPCA',
            Str::startsWith($normalized, ['LFT', 'TESOURO SELIC']) => 'SELIC',
            default => 'CDI',
        };
    }

    /**
     * Total de proventos e créditos em dinheiro recebidos (dividendos, JCP,
     * rendimentos, juros, amortizações e leilão de frações). Lançamentos em
     * débito (estornos) subtraem.
     */
    public function dividendsReceived(): float
    {
        return (float) $this->transactions
            ->whereIn('type', self::CASH_INCOME_TYPES)
            ->sum(fn (Transaction $t) => ($t->isCredit() ? 1 : -1) * (float) $t->total_amount);
    }

    /** Variação percentual sem proventos (só preço/correção): valor atual vs custo. */
    public function percentChange(): ?float
    {
        $purchase = $this->purchaseValue();

        if ($purchase <= 0) {
            return null;
        }

        return ($this->currentValue() - $purchase) / $purchase * 100;
    }

    /** Variação percentual incluindo proventos recebidos. */
    public function percentChangeWithDividends(): ?float
    {
        $purchase = $this->purchaseValue();

        if ($purchase <= 0) {
            return null;
        }

        return ($this->currentValue() + $this->dividendsReceived() - $purchase) / $purchase * 100;
    }

    /**
     * Filtra apenas ativos com posição líquida (quantidade) maior que zero.
     *
     * O grupamento redefine a posição em vez de somar/subtrair, o que não é
     * expressável num agregado SQL simples; calcula a posição em PHP (mesma
     * regra de positionQuantity) e filtra por id. Clona a própria query para
     * herdar os filtros já aplicados (tenant, tipo...) e não computar ativos
     * de outros tenants.
     */
    public function scopeWherePositionPositive(Builder $query): Builder
    {
        $ids = (clone $query)
            ->with('transactions:id,asset_id,type,transaction_date,quantity,direction')
            ->get([$query->qualifyColumn('id')])
            ->filter(fn (Asset $asset): bool => $asset->positionQuantity() > 1e-9)
            ->modelKeys();

        return $query->whereIn($query->qualifyColumn('id'), $ids);
    }
}
