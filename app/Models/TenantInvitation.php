<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Convite do modo família: dá acesso de outra pessoa à mesma carteira. */
#[Fillable(['tenant_id', 'email', 'token', 'invited_by', 'accepted_at', 'expires_at'])]
class TenantInvitation extends Model
{
    /** Validade padrão de um convite, em dias. */
    public const VALID_DAYS = 7;

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function createFor(Tenant $tenant, string $email, User $inviter): self
    {
        return self::create([
            'tenant_id' => $tenant->getKey(),
            'email' => mb_strtolower(trim($email)),
            'token' => Str::random(48),
            'invited_by' => $inviter->getKey(),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }
}
