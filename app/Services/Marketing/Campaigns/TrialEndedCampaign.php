<?php

namespace App\Services\Marketing\Campaigns;

use App\Models\User;
use App\Services\Marketing\Campaign;

/**
 * Dia seguinte ao fim do trial — última mensagem do funil de conversão,
 * lembrando que os dados continuam guardados e o retorno é de um clique.
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
