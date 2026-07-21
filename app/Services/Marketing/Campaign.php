<?php

namespace App\Services\Marketing;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Uma campanha do funil de e-mail marketing. Cada campanha declara sua janela
 * ideal (dias após o cadastro) e uma condição opcional de segmento; o
 * CampaignManager decide qual enviar e garante no máximo um envio por
 * usuário/campanha e um e-mail por dia.
 */
abstract class Campaign
{
    /** Identificador persistido em marketing_email_sends.campaign (máx. 50 chars). */
    abstract public function key(): string;

    /** Dia (após o cadastro) em que a campanha deve ser enviada. */
    abstract public function dueDay(): int;

    abstract public function subject(User $user): string;

    /** Texto oculto exibido como prévia nos clientes de e-mail. */
    abstract public function preheader(User $user): string;

    abstract public function headline(User $user): string;

    /**
     * @return list<string>
     */
    abstract public function paragraphs(User $user): array;

    /**
     * Itens de lista opcionais exibidos entre os parágrafos e o CTA.
     *
     * @return list<string>
     */
    public function bullets(User $user): array
    {
        return [];
    }

    abstract public function ctaLabel(): string;

    public function ctaUrl(): string
    {
        return url('/app');
    }

    /** Condição de segmento além da janela de dias (ex.: "carteira ainda vazia"). */
    public function appliesTo(User $user): bool
    {
        return true;
    }

    /**
     * Devida quando o usuário está dentro da janela [dueDay, dueDay + grace]
     * e a condição de segmento é atendida.
     */
    public function isDue(User $user, CarbonInterface $now): bool
    {
        $days = (int) $user->created_at->startOfDay()->diffInDays($now->copy()->startOfDay());

        return $days >= $this->dueDay()
            && $days <= $this->dueDay() + (int) config('marketing.grace_days')
            && $this->appliesTo($user);
    }

    protected function firstName(User $user): string
    {
        return Str::of($user->name)->trim()->before(' ')->ucfirst()->toString();
    }
}
