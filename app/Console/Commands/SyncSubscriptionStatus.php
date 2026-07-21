<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Materializa expirações: trials vencidos e assinaturas canceladas cujo
 * período pago terminou viram "expired". Roda diariamente antes dos e-mails
 * de marketing, para que a segmentação enxergue o status correto.
 */
class SyncSubscriptionStatus extends Command
{
    protected $signature = 'subscriptions:sync-status';

    protected $description = 'Expira trials vencidos e assinaturas canceladas com período encerrado';

    public function handle(): int
    {
        $expiredTrials = Subscription::query()
            ->where('status', SubscriptionStatus::Trialing)
            ->where('trial_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::Expired]);

        $endedCancellations = Subscription::query()
            ->where('status', SubscriptionStatus::Canceled)
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::Expired]);

        $this->info("Trials expirados: {$expiredTrials} · Cancelamentos encerrados: {$endedCancellations}");

        return self::SUCCESS;
    }
}
