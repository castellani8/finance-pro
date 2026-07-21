<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioSnapshot extends Model
{
    protected $table = 'portfolio_snapshots';

    protected $fillable = ['tenant_id', 'date', 'invested', 'current_value'];

    protected $casts = [
        'invested' => 'decimal:2',
        'current_value' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
