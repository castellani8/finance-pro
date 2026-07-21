<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de todo e-mail enviado pela aplicação (marketing, verificação,
 * alertas...). Alimentado automaticamente pelo listener de MessageSending;
 * read_at é marcado pelo pixel de rastreio.
 */
#[Fillable(['from', 'to', 'user_id', 'tag', 'subject', 'html_body', 'action_url', 'error_log', 'read_at'])]
class EmailLog extends Model
{
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
