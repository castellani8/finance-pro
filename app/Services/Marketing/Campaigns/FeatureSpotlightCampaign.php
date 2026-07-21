<?php

namespace App\Services\Marketing\Campaigns;

use App\Models\User;
use App\Services\Marketing\Campaign;

/**
 * Dia 7 — meio do trial: mostra os recursos que criam hábito (benchmarks,
 * renda passiva, alertas) para ancorar o valor antes da conversa de preço.
 */
class FeatureSpotlightCampaign extends Campaign
{
    public function key(): string
    {
        return 'spotlight_d7';
    }

    public function dueDay(): int
    {
        return 7;
    }

    public function subject(User $user): string
    {
        return 'Sua carteira está batendo o CDI, '.$this->firstName($user).'?';
    }

    public function preheader(User $user): string
    {
        return 'Compare sua carteira com CDI e IBOV e acompanhe sua renda passiva.';
    }

    public function headline(User $user): string
    {
        return 'Três recursos que separam quem controla de quem só olha.';
    }

    public function paragraphs(User $user): array
    {
        return [
            'Você está na metade do período de teste — hora de conhecer o que a Milia Invest faz por você todos os dias, mesmo quando você não abre o painel.',
        ];
    }

    public function bullets(User $user): array
    {
        return [
            'Benchmarks — sua carteira contra os mesmos aportes rendendo 100% do CDI e contra o IBOV. Saiba se está valendo a pena.',
            'Renda passiva — defina uma meta mensal e acompanhe quanto falta para os proventos pagarem suas contas.',
            'Alertas automáticos — vencimentos de renda fixa, contratos terminando e contas negativas chegam no painel e no seu e-mail às 8h.',
        ];
    }

    public function ctaLabel(): string
    {
        return 'Ver minha evolução';
    }
}
