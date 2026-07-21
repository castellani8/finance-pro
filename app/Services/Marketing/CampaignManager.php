<?php

namespace App\Services\Marketing;

use App\Mail\MarketingCampaignMail;
use App\Models\MarketingEmailSend;
use App\Models\User;
use App\Services\Marketing\Campaigns\ActivationNudgeCampaign;
use App\Services\Marketing\Campaigns\FeatureSpotlightCampaign;
use App\Services\Marketing\Campaigns\TrialEndedCampaign;
use App\Services\Marketing\Campaigns\TrialEndingCampaign;
use App\Services\Marketing\Campaigns\WelcomeCampaign;
use App\Services\Marketing\Campaigns\WinbackCampaign;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;

/**
 * Orquestra o funil de e-mail marketing: decide a campanha devida de cada
 * usuário (no máximo uma por dia), envia e registra. O registro em
 * marketing_email_sends + unique é o que garante idempotência mesmo com
 * cron e listener concorrendo.
 */
class CampaignManager
{
    /**
     * Ordem = prioridade quando mais de uma campanha estiver devida
     * (conversão vem antes de conteúdo).
     *
     * @return list<Campaign>
     */
    public function campaigns(): array
    {
        return [
            new WelcomeCampaign,
            new TrialEndingCampaign,
            new TrialEndedCampaign,
            new ActivationNudgeCampaign,
            new FeatureSpotlightCampaign,
            new WinbackCampaign,
        ];
    }

    /** A campanha mais prioritária devida ao usuário e ainda não enviada. */
    public function dueFor(User $user): ?Campaign
    {
        if (! $this->canReceive($user)) {
            return null;
        }

        $sent = MarketingEmailSend::query()
            ->where('user_id', $user->getKey())
            ->pluck('campaign')
            ->all();

        foreach ($this->campaigns() as $campaign) {
            if (! in_array($campaign->key(), $sent, true) && $campaign->isDue($user, now())) {
                return $campaign;
            }
        }

        return null;
    }

    /**
     * Envia (via fila) e registra. Retorna false se o usuário não pode
     * receber ou se a campanha já havia sido registrada por outro processo.
     */
    public function send(User $user, Campaign $campaign): bool
    {
        if (! $this->canReceive($user)) {
            return false;
        }

        try {
            MarketingEmailSend::create([
                'user_id' => $user->getKey(),
                'campaign' => $campaign->key(),
                'sent_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        Mail::to($user)->queue(new MarketingCampaignMail($user, $campaign));

        return true;
    }

    public function canReceive(User $user): bool
    {
        return (bool) config('marketing.enabled')
            && $user->marketing_emails_unsubscribed_at === null;
    }
}
