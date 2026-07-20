<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class AssetPriceHistory extends Model
{
    use HasFactory;
    protected $table = 'asset_price_history';
    protected $fillable = ['ticker', 'date', 'price_open', 'price_close', 'price_high', 'price_low', 'source'];

}
