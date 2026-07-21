<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Marketing\CampaignManager;
use App\Services\Marketing\Campaigns\WelcomeCampaign;
use Filament\Auth\Events\Registered;

/**
 * Dispara o e-mail de boas-vindas na hora do cadastro. O cron diário cobre
 * como rede de segurança (a unique em marketing_email_sends evita duplicar).
 */
class SendMarketingWelcomeEmail
{
    public function __construct(
        private CampaignManager $campaigns,
    ) {}

    public function handle(Registered $event): void
    {
        $user = $event->getUser();

        if (! $user instanceof User) {
            return;
        }

        $this->campaigns->send($user, new WelcomeCampaign);
    }
}
