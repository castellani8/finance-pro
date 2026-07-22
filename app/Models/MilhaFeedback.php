<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feedback de clientes coletado pela Milha no painel — a matéria-prima para
 * saber do que reclamam, o que pedem e o que elogiam.
 */
class MilhaFeedback extends Model
{
    protected $table = 'milha_feedback';

    public const TIPOS = ['reclamacao', 'sugestao', 'elogio', 'bug', 'outro'];

    protected $fillable = ['tenant_id', 'user_id', 'tipo', 'mensagem', 'contexto'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
