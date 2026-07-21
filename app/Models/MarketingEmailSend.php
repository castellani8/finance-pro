<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de campanha de e-mail marketing enviada a um usuário. A unique
 * (user_id, campaign) garante que nenhuma campanha é enviada duas vezes.
 */
#[Fillable(['user_id', 'campaign', 'sent_at'])]
class MarketingEmailSend extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
