<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contrato recorrente (aluguel mensal, assinatura...): o gerador materializa
 * cada vencimento em uma Transaction real com source='recurring'.
 */
class RecurringTransaction extends Model
{
    protected $table = 'recurring_transactions';

    protected $fillable = [
        'tenant_id', 'asset_id', 'company_id', 'type', 'description', 'category',
        'amount', 'day_of_month', 'starts_on', 'ends_on', 'active', 'last_generated_on',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'last_generated_on' => 'date',
        'active' => 'boolean',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
