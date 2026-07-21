<?php

namespace App\Services\Marketing\Campaigns;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Services\Marketing\Campaign;
use Carbon\CarbonInterface;

/**
 * Logo após o trial expirar (assinatura em "expired") — última mensagem do
 * funil de conversão, lembrando que os dados continuam guardados. Enviada em
 * até 3 dias após a expiração; depois disso o winback assume.
 */
class TrialEndedCampaign extends Campaign
{
    public function key(): string
    {
        return 'trial_ended';
    }

    public function dueDay(): int
    {
        return (int) config('landing.plan.trial_days') + 1;
    }

    public function isDue(User $user, CarbonInterface $now): bool
    {
        $subscription = $user->subscription;

        if ($subscription === null || $subscription->status !== SubscriptionStatus::Expired) {
            return false;
        }

        $daysSinceEnd = (int) $subscription->trial_ends_at->startOfDay()->diffInDays($now->copy()->startOfDay());

        return $daysSinceEnd >= 0 && $daysSinceEnd <= 3;
    }

    public function subject(User $user): string
    {
        return 'Seu teste terminou — seu patrimônio continua aqui';
    }

    public function preheader(User $user): string
    {
        return 'Tudo o que você organizou está guardado. Volte quando quiser.';
    }

    public function headline(User $user): string
    {
        return 'Seus dados estão guardados. A decisão é sua.';
    }

    public function paragraphs(User $user): array
    {
        return [
            'Olá, '.$this->firstName($user).'. Seus '.config('landing.plan.trial_days').' dias de teste da Milia Invest chegaram ao fim — e tudo o que você construiu continua guardado com segurança.',
            'Para seguir acompanhando sua carteira, seus proventos e seus alertas, basta continuar com o plano único de R$ '.config('landing.plan.price').'/mês. Sem fidelidade, sem multa: se não fizer sentido, você cancela e ainda pode exportar todos os seus dados (LGPD).',
        ];
    }

    public function ctaLabel(): string
    {
        return 'Retomar meu painel';
    }
}
