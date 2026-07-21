<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Marketing\CampaignManager;
use Illuminate\Console\Command;

/**
 * Régua diária de e-mail marketing: para cada usuário elegível, envia a
 * campanha devida mais prioritária (no máximo uma por dia). Idempotente —
 * pode rodar quantas vezes for preciso.
 */
class SendMarketingCampaigns extends Command
{
    protected $signature = 'marketing:send-campaigns {--dry-run : Lista o que seria enviado sem enviar}';

    protected $description = 'Envia as campanhas de e-mail marketing devidas do dia';

    public function handle(CampaignManager $campaigns): int
    {
        if (! config('marketing.enabled')) {
            $this->warn('E-mail marketing desabilitado (MARKETING_EMAILS_ENABLED=false).');

            return self::SUCCESS;
        }

        $sent = 0;

        User::query()
            ->whereNull('marketing_emails_unsubscribed_at')
            ->with(['tenants', 'subscription'])
            ->chunkById(200, function ($users) use ($campaigns, &$sent): void {
                foreach ($users as $user) {
                    $campaign = $campaigns->dueFor($user);

                    if ($campaign === null) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("[dry-run] {$user->email} → {$campaign->key()}");

                        continue;
                    }

                    if ($campaigns->send($user, $campaign)) {
                        $this->line("{$user->email} → {$campaign->key()}");
                        $sent++;
                    }
                }
            });

        $this->info("Campanhas enviadas: {$sent}");

        return self::SUCCESS;
    }
}
