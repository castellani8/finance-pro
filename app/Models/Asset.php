<?php

namespace App\Models;

use App\Services\IndexAccumulator;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Quantidade líquida em carteira: compras e bonificações somam, vendas subtraem.
     * Proventos (dividendos, JCP, rendimentos, juros) não alteram a posição.
     */
    public function positionQuantity(): float
    {
        return (float) $this->transactions->reduce(function (float $carry, Transaction $t): float {
            return match ($t->type) {
                'BUY', 'BONUS' => $carry + (float) $t->quantity,
                'SELL' => $carry - (float) $t->quantity,
                default => $carry,
            };
        }, 0.0);
    }

    /** Preço médio ponderado das compras. */
    public function averageBuyPrice(): float
    {
        $buys = $this->transactions->where('type', 'BUY');
        $quantity = (float) $buys->sum(fn (Transaction $t) => (float) $t->quantity);

        if ($quantity <= 0) {
            return 0.0;
        }

        return (float) $buys->sum(fn (Transaction $t) => (float) $t->total_amount) / $quantity;
    }

    /** Valor de compra: custo da posição atual (preço médio x quantidade atual). */
    public function purchaseValue(): float
    {
        return $this->averageBuyPrice() * $this->positionQuantity();
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
     * Valor atual da posição.
     * - Renda fixa: valor de compra corrigido pelo índice (CDI/IPCA/SELIC).
     * - Demais: quantidade x último preço do histórico; sem cotação, cai para o custo.
     */
    public function currentValue(): float
    {
        if ($this->type === 'FIXED_INCOME') {
            return $this->fixedIncomeCurrentValue();
        }

        $price = $this->currentUnitPrice();

        if ($price === null) {
            return $this->purchaseValue();
        }

        return $this->positionQuantity() * $price;
    }

    /**
     * Corrige cada compra pelo fator acumulado do indexador desde a data do aporte,
     * proporcional à fração ainda mantida em carteira.
     */
    protected function fixedIncomeCurrentValue(): float
    {
        $buys = $this->transactions->where('type', 'BUY');
        $boughtQuantity = (float) $buys->sum(fn (Transaction $t) => (float) $t->quantity);

        if ($boughtQuantity <= 0) {
            return $this->purchaseValue();
        }

        $heldFraction = max(0.0, min(1.0, $this->positionQuantity() / $boughtQuantity));

        if ($heldFraction <= 0) {
            return 0.0;
        }

        $indexer = $this->indexer() ?? 'CDI';
        $percent = $this->indexPercent();
        $spread = $this->spread();
        $accumulator = app(IndexAccumulator::class);

        $corrected = $buys->reduce(function (float $carry, Transaction $t) use ($accumulator, $indexer, $percent, $spread): float {
            $rawDate = $t->getRawOriginal('transaction_date');
            $from = $rawDate ? Carbon::parse($rawDate)->toDateString() : '';
            $factor = $from !== '' ? $accumulator->factorSince($indexer, $from, $percent, $spread) : 1.0;

            return $carry + ((float) $t->total_amount * $factor);
        }, 0.0);

        return $corrected * $heldFraction;
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
            return 'Prefixado ' . $fmt($spread) . '% a.a.';
        }

        $label = $percent != 100.0 ? $fmt($percent) . '% ' . $indexer : $indexer;

        if ($percent == 100.0 && $spread == 0.0) {
            $label = '100% ' . $indexer;
        }

        if ($spread != 0.0) {
            $label .= ' + ' . $fmt($spread) . '%';
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

    /** Total de proventos recebidos (dividendos, JCP, rendimentos de FII e juros). */
    public function dividendsReceived(): float
    {
        return (float) $this->transactions
            ->whereIn('type', ['DIVIDEND', 'JCP', 'INCOME', 'INTEREST'])
            ->sum(fn (Transaction $t) => (float) $t->total_amount);
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

    /** Filtra apenas ativos com posição líquida (quantidade) maior que zero. */
    public function scopeWherePositionPositive(Builder $query): Builder
    {
        return $query->whereRaw(
            "(select coalesce(sum(case "
            . "when transactions.type in ('BUY', 'BONUS') then transactions.quantity "
            . "when transactions.type = 'SELL' then -transactions.quantity "
            . "else 0 end), 0) "
            . "from transactions where transactions.asset_id = assets.id) > 0"
        );
    }
}
