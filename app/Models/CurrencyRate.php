<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cotação diária de câmbio (PTAX venda do BCB), sincronizada pelo command
 * marketing:sync-currencies.
 */
class CurrencyRate extends Model
{
    protected $fillable = ['currency', 'date', 'rate'];

    protected $casts = [
        'rate' => 'decimal:6',
    ];
}
