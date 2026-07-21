<?php

namespace App\Services\Marketing\Campaigns;

use App\Models\User;
use App\Services\Marketing\Campaign;
use Carbon\CarbonInterface;

/**
 * 3 dias antes do fim do trial — a conversa de conversão, com urgência
 * honesta e o preço ancorado no valor construído durante o teste.
 */
class TrialEndingCampaign extends Campaign
{
    public function key(): string
    {
        return 'trial_ending';
    }

    public function dueDay(): int
    {
        return (int) config('landing.plan.trial_days') - 3;
    }

    /** Janela apertada: depois do fim do trial este e-mail perde o sentido. */
    public function isDue(User $user, CarbonInterface $now): bool
    {
        $days = (int) $user->created_at->startOfDay()->diffInDays($now->copy()->startOfDay());

        return $days >= $this->dueDay()
            && $days < (int) config('landing.plan.trial_days')
            && $this->appliesTo($user);
    }

    public function subject(User $user): string
    {
        return 'Seus dias grátis estão acabando, '.$this->firstName($user);
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
            'Seu período de teste da Milia Invest termina em poucos dias. Depois dele, tudo o que você organizou — carteira, proventos, fluxo de caixa, relatório de IR — continua exatamente onde está, por R$ '.config('landing.plan.price').' por mês.',
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
