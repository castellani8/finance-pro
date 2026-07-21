<?php

namespace App\Services\Marketing\Campaigns;

use App\Models\User;
use App\Services\Marketing\Campaign;

/**
 * Boas-vindas: enviada na hora do cadastro (listener) — o cron cobre como
 * rede de segurança se o envio imediato falhar.
 */
class WelcomeCampaign extends Campaign
{
    public function key(): string
    {
        return 'welcome';
    }

    public function dueDay(): int
    {
        return 0;
    }

    public function subject(User $user): string
    {
        return 'Bem-vindo à Milia Invest, '.$this->firstName($user).' — seus '.config('landing.plan.trial_days').' dias começam agora';
    }

    public function preheader(User $user): string
    {
        return 'Em poucos minutos você vê todo o seu patrimônio em um só painel.';
    }

    public function headline(User $user): string
    {
        return 'Todo o seu patrimônio. Uma única visão.';
    }

    public function paragraphs(User $user): array
    {
        return [
            'Olá, '.$this->firstName($user).'! Sua conta está pronta e você tem '.config('landing.plan.trial_days').' dias para explorar tudo — sem cartão de crédito.',
            'O caminho mais rápido para o primeiro "uau": importe a planilha de movimentação da B3 (Extratos → Movimentação) e veja posições, proventos e rentabilidade aparecerem na hora.',
        ];
    }

    public function bullets(User $user): array
    {
        return [
            'Cadastre suas contas — banco, corretora ou caixa',
            'Importe seus ativos da B3 ou adicione imóveis e veículos',
            'Defina sua meta de renda passiva e acompanhe os proventos',
        ];
    }

    public function ctaLabel(): string
    {
        return 'Abrir meu painel';
    }
}
