<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Valor atual: quantidade x último preço do histórico.
     * Sem cotação (ex: renda fixa), cai para o valor de compra.
     */
    public function currentValue(): float
    {
        $price = $this->currentUnitPrice();

        if ($price === null) {
            return $this->purchaseValue();
        }

        return $this->positionQuantity() * $price;
    }
}
