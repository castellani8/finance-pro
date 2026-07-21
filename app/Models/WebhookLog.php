<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de cada webhook recebido dos gateways de pagamento — trilha de
 * auditoria e trava de idempotência do processamento assíncrono.
 */
#[Fillable(['source', 'event', 'payload', 'status', 'error'])]
class WebhookLog extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
