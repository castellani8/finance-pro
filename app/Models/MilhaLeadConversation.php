<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversa de um lead com a Milha vendedora na landing: mensagens, tokens
 * gastos e cliques no CTA de cadastro — base das métricas de conversão.
 */
class MilhaLeadConversation extends Model
{
    protected $fillable = [
        'session_id', 'ip', 'user_agent', 'cta_clicks', 'cta_first_clicked_at',
        'prompt_tokens', 'completion_tokens',
    ];

    protected $casts = [
        'cta_first_clicked_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(MilhaLeadMessage::class, 'conversation_id');
    }

    public function registerCtaClick(): void
    {
        $this->forceFill([
            'cta_clicks' => $this->cta_clicks + 1,
            'cta_first_clicked_at' => $this->cta_first_clicked_at ?? now(),
        ])->save();
    }
}
