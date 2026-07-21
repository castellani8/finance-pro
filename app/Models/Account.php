<?php

namespace App\Models;

use App\Services\CurrencyConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Conta de dinheiro (banco, corretora, caixa físico). O saldo é o saldo
 * inicial mais o efeito-caixa dos lançamentos vinculados, e entra no
 * patrimônio total.
 */
class Account extends Model
{
    use LogsActivity;

    /** Auditoria: registra criações/edições/exclusões dos campos preenchíveis. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public const KIND_LABELS = [
        'bank' => 'Conta bancária',
        'broker' => 'Conta na corretora',
        'cash' => 'Caixa / Dinheiro físico',
        'other' => 'Outra',
    ];

    protected $fillable = ['tenant_id', 'company_id', 'name', 'kind', 'opening_balance', 'currency'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
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

    /** Saldo na data (Y-m-d, inclusiva); null = hoje. */
    public function balanceAt(?string $until = null): float
    {
        return (float) $this->opening_balance + (float) $this->transactions
            ->filter(fn (Transaction $t): bool => $until === null
                || substr((string) $t->getRawOriginal('transaction_date'), 0, 10) <= $until)
            ->sum(fn (Transaction $t): float => $t->cashDelta());
    }

    public function balance(): float
    {
        return $this->balanceAt();
    }

    /**
     * Saldo convertido para BRL pela cotação da data — dólares em conta valem
     * o câmbio do dia, então o saldo inteiro converte pela taxa da data.
     */
    public function balanceInBrlAt(?string $until = null): float
    {
        return app(CurrencyConverter::class)
            ->toBrl($this->balanceAt($until), $this->currency, $until);
    }
}
