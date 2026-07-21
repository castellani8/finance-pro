<?php

namespace App\Models;

use App\Enums\FlowDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Transaction extends Model
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

    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'type',
        'transaction_date',
        'quantity',
        'unit_price',
        'total_amount',
        'fees',
        'direction',
        'movement',
        'institution',
        'source',
        'external_hash',
        'notes',
        'company_id',
        'category',
        'recurring_transaction_id',
        'account_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'quantity' => 'decimal:6',
        'unit_price' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'fees' => 'decimal:4',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Sentido do fluxo, resolvido do texto salvo com fallback pelo tipo. */
    public function flowDirection(): FlowDirection
    {
        return FlowDirection::resolve($this->direction, (string) $this->type);
    }

    /** Entrada (Crédito) ou saída (Débito). */
    public function isCredit(): bool
    {
        return $this->flowDirection()->isCredit();
    }

    /**
     * Efeito no CAIXA da conta vinculada: comprar um ativo tira dinheiro da
     * conta (mesmo sendo "Crédito" de custódia); vender coloca; renda entra;
     * despesa sai.
     */
    public function cashDelta(): float
    {
        $sign = $this->flowDirection()->sign();

        if (in_array($this->type, Asset::POSITION_TYPES, true)) {
            $sign = -$sign;
        }

        return $sign * (float) $this->total_amount;
    }
}
