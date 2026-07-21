<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo dos tickers negociáveis na B3, sincronizado da brapi pelo command
 * marketing:sync-tickers. Alimenta o select de ticker do formulário de ativos.
 */
class B3ListedTicker extends Model
{
    protected $table = 'b3_listed_tickers';

    protected $fillable = ['ticker', 'name', 'asset_kind', 'sector'];

    /** Rótulo exibido no select: "PETR4 — PETROBRAS PN". */
    public function label(): string
    {
        return $this->name ? "{$this->ticker} — {$this->name}" : $this->ticker;
    }
}
