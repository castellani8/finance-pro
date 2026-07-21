<?php

namespace App\Services\Marketing\Campaigns;

use App\Models\User;
use App\Services\Marketing\Campaign;
use Carbon\CarbonInterface;
use Spatie\Activitylog\Models\Activity;

/**
 * Dia 30 — winback: só para quem está inativo há pelo menos 14 dias
 * (nenhuma alteração registrada na auditoria). Janela larga para pegar
 * quem foi esfriando aos poucos.
 */
class WinbackCampaign extends Campaign
{
    private const INACTIVE_DAYS = 14;

    public function key(): string
    {
        return 'winback_d30';
    }

    public function dueDay(): int
    {
        return 30;
    }

    /** Janela larga (duas semanas) — inatividade é o critério que manda. */
    public function isDue(User $user, CarbonInterface $now): bool
    {
        $days = (int) $user->created_at->startOfDay()->diffInDays($now->copy()->startOfDay());

        return $days >= $this->dueDay()
            && $days <= $this->dueDay() + 14
            && $this->appliesTo($user);
    }

    public function appliesTo(User $user): bool
    {
        $lastActivity = Activity::query()
            ->where('causer_type', $user->getMorphClass())
            ->where('causer_id', $user->getKey())
            ->latest('created_at')
            ->value('created_at');

        return $lastActivity === null
            || $lastActivity->lt(now()->subDays(self::INACTIVE_DAYS));
    }

    public function subject(User $user): string
    {
        return 'Enquanto você esteve fora, sua carteira continuou mudando';
    }

    public function preheader(User $user): string
    {
        return 'Cotações, câmbio e alertas seguem rodando todos os dias no seu painel.';
    }

    public function headline(User $user): string
    {
        return 'Seu patrimônio não para — seu acompanhamento também não deveria.';
    }

    public function paragraphs(User $user): array
    {
        return [
            'Olá, '.$this->firstName($user).'. Faz um tempo que você não abre a Milia Invest — e nesse período as cotações se moveram, proventos podem ter caído na conta e vencimentos se aproximaram.',
            'Seu painel continua atualizando tudo sozinho, todos os dias. Basta entrar para ver o retrato atual do seu patrimônio.',
        ];
    }

    public function ctaLabel(): string
    {
        return 'Ver como está minha carteira';
    }
}
