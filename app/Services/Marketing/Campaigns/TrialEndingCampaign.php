<?php

namespace App\Services\Marketing\Campaigns;

use App\Models\User;
use App\Services\Marketing\Campaign;
use Carbon\CarbonInterface;

/**
 * 3 dias antes do fim do trial (ancorado na assinatura real) — a conversa de
 * conversão, com urgência honesta e o preço ancorado no valor construído.
 * Quem já assinou nunca recebe.
 */
class TrialEndingCampaign extends Campaign
{
    private const DAYS_BEFORE_END = 3;

    public function key(): string
    {
        return 'trial_ending';
    }

    public function dueDay(): int
    {
        return (int) config('landing.plan.trial_days') - self::DAYS_BEFORE_END;
    }

    /** Ancorada em subscription.trial_ends_at; janela fecha quando o trial vence. */
    public function isDue(User $user, CarbonInterface $now): bool
    {
        $subscription = $user->subscription;

        return $subscription !== null
            && $subscription->isTrialing()
            && $subscription->trialDaysLeft() <= self::DAYS_BEFORE_END;
    }

    public function subject(User $user): string
    {
        $days = $user->subscription?->trialDaysLeft() ?? self::DAYS_BEFORE_END;

        return $days <= 1
            ? 'Último dia do seu teste grátis, '.$this->firstName($user)
            : "Faltam {$days} dias de teste grátis, ".$this->firstName($user);
    }

    public function preheader(User $user): string
    {
        return 'Continue com tudo por R$ '.config('landing.plan.price').'/mês — menos de R$ 0,70 por dia.';
    }

    public function headline(User $user): string
    {
        return 'Não volte para as planilhas.';
    }

    public function paragraphs(User $user): array
    {
        return [
            'Seu período de teste da Milia Invest termina em breve. Depois dele, tudo o que você organizou — carteira, proventos, fluxo de caixa, relatório de IR — continua exatamente onde está, por R$ '.config('landing.plan.price').' por mês.',
            'Isso é menos de R$ 0,70 por dia para nunca mais perder o controle do seu patrimônio.',
        ];
    }

    public function bullets(User $user): array
    {
        return [
            'Cotações da B3 e câmbio atualizados todos os dias',
            'Alertas de vencimentos e contas direto no seu e-mail',
            'Relatório anual pronto para o Imposto de Renda',
            'Cancele quando quiser, sem multa',
        ];
    }

    public function ctaLabel(): string
    {
        return 'Continuar com a Milia Invest';
    }
}
