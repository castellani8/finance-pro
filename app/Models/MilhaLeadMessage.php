<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilhaLeadMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'prompt_tokens', 'completion_tokens'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MilhaLeadConversation::class, 'conversation_id');
    }
}
