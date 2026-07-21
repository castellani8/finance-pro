<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transaction extends Model
{
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

    /**
     * Sentido do fluxo: entrada (Crédito) ou saída (Débito). Lançamentos manuais
     * sem direction caem no tipo (SELL sai, o resto entra).
     */
    public function isCredit(): bool
    {
        if ($this->direction !== null && $this->direction !== '') {
            return Str::upper(Str::ascii($this->direction)) === 'CREDITO';
        }

        return $this->type !== 'SELL';
    }
}
