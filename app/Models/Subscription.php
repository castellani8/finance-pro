<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assinatura do plano único (uma por usuário). O acesso ao painel deriva de
 * hasAccess(); o comando subscriptions:sync-status materializa expirações, e
 * os webhooks do Asaas (futuro) atualizarão status e período diretamente.
 */
#[Fillable([
    'user_id',
    'status',
    'trial_ends_at',
    'current_period_ends_at',
    'price',
    'canceled_at',
    'asaas_customer_id',
    'asaas_subscription_id',
])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Cria o trial de um usuário recém-cadastrado. */
    public static function startTrialFor(User $user): self
    {
        return self::create([
            'user_id' => $user->getKey(),
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays((int) config('landing.plan.trial_days', 15)),
            'price' => (float) str_replace(',', '.', (string) config('landing.plan.price', '19,90')),
        ]);
    }

    public function isTrialing(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at->isFuture();
    }

    /** O usuário ainda pode usar o painel? */
    public function hasAccess(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trial_ends_at->isFuture(),
            SubscriptionStatus::Active, SubscriptionStatus::PastDue => true,
            SubscriptionStatus::Canceled => $this->current_period_ends_at?->isFuture() ?? false,
            SubscriptionStatus::Expired => false,
        };
    }

    public function trialDaysLeft(): int
    {
        return max(0, (int) now()->startOfDay()->diffInDays($this->trial_ends_at->copy()->startOfDay()));
    }
}
