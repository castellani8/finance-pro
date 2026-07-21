<?php

namespace App\Jobs;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\Payments\Data\Plan;
use App\Services\Payments\Data\WebhookEvent;
use App\Services\Payments\Enums\GatewayWebhookEvent;
use App\Services\Payments\PaymentGatewayManager;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processa um webhook de assinatura já normalizado pelo gateway e reflete o
 * resultado na Subscription do usuário. Independe do provedor: toda a lógica
 * específica fica no driver (parseWebhook); aqui lidamos apenas com o
 * WebhookEvent canônico.
 */
class ProcessSubscriptionWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $webhookLogId,
        public string $gateway,
        public array $payload,
    ) {}

    public function handle(PaymentGatewayManager $manager): void
    {
        try {
            // Trava a linha do log durante o processamento: dois webhooks
            // simultâneos do mesmo evento não são aplicados em duplicidade
            // (o segundo espera, relê 'processed' e sai).
            DB::transaction(function () use ($manager) {
                $log = WebhookLog::lockForUpdate()->find($this->webhookLogId);

                if ($log?->status === 'processed') {
                    return;
                }

                $event = $manager->driver($this->gateway)->parseWebhook($this->payload);

                if ($event->type === GatewayWebhookEvent::UNKNOWN) {
                    $log?->update(['status' => 'ignored']);

                    return;
                }

                $subscription = $this->resolveSubscription($event);

                if (! $subscription) {
                    $this->markFailed($log, 'Assinatura não encontrada para o evento de webhook');

                    return;
                }

                $this->apply($subscription, $event);

                $log?->update(['status' => 'processed']);
            });
        } catch (\Throwable $e) {
            // A transação foi revertida; registra a falha fora dela e relança
            // para que a fila tente novamente.
            $this->markFailed(WebhookLog::find($this->webhookLogId), $e->getMessage());

            throw $e;
        }
    }

    private function resolveSubscription(WebhookEvent $event): ?Subscription
    {
        if ($userId = $event->userId()) {
            $subscription = Subscription::where('user_id', $userId)->first();

            if ($subscription) {
                return $subscription;
            }
        }

        if ($event->subscriptionId) {
            $subscription = Subscription::where('asaas_subscription_id', $event->subscriptionId)->first();

            if ($subscription) {
                return $subscription;
            }
        }

        if ($event->customerId) {
            return Subscription::where('asaas_customer_id', $event->customerId)->first();
        }

        return null;
    }

    private function apply(Subscription $subscription, WebhookEvent $event): void
    {
        match ($event->type) {
            GatewayWebhookEvent::PAYMENT_CREATED => $this->onPaymentCreated($subscription, $event),
            GatewayWebhookEvent::PAYMENT_CONFIRMED => $this->onPaymentConfirmed($subscription),
            GatewayWebhookEvent::PAYMENT_OVERDUE => $this->onPaymentOverdue($subscription),
            GatewayWebhookEvent::PAYMENT_REFUNDED => $this->onPaymentRefunded($subscription),
            GatewayWebhookEvent::SUBSCRIPTION_CANCELLED => $this->onSubscriptionCancelled($subscription),
            GatewayWebhookEvent::UNKNOWN => null,
        };
    }

    private function onPaymentCreated(Subscription $subscription, WebhookEvent $event): void
    {
        // Guarda o link da fatura da nova cobrança — é o que a página de
        // assinatura oferece para pagar em PIX/boleto.
        $invoiceUrl = $event->raw['payment']['invoiceUrl'] ?? null;

        if ($invoiceUrl) {
            $subscription->update(['latest_invoice_url' => $invoiceUrl]);
        }

        Log::info("[{$this->gateway}] Cobrança criada para user #{$subscription->user_id}");
    }

    private function onPaymentConfirmed(Subscription $subscription): void
    {
        // Renovação: se ainda há período pago à frente, soma o novo ciclo a
        // partir do fim dele para não descartar dias já pagos. Nunca parte de
        // uma data no passado (o dueDate do evento pode já ter vencido).
        $base = $subscription->current_period_ends_at?->isFuture()
            ? $subscription->current_period_ends_at
            : Carbon::now();

        $cycle = Plan::find(config('subscription.default_plan'))?->cycle;

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => $cycle?->addTo($base) ?? $base->copy()->addMonth(),
            'canceled_at' => null,
        ]);

        $this->notifyUser(
            $subscription->user,
            fn (Notification $n) => $n
                ->title('Pagamento confirmado!')
                ->body('Sua assinatura está ativa.')
                ->success(),
        );

        Log::info("[{$this->gateway}] Pagamento confirmado para user #{$subscription->user_id}");
    }

    private function onPaymentOverdue(Subscription $subscription): void
    {
        $subscription->update(['status' => SubscriptionStatus::PastDue]);

        $this->notifyUser(
            $subscription->user,
            fn (Notification $n) => $n
                ->title('Pagamento atrasado')
                ->body('Não identificamos seu pagamento. Regularize para manter o acesso.')
                ->warning(),
        );

        Log::info("[{$this->gateway}] Pagamento atrasado para user #{$subscription->user_id}");
    }

    private function onPaymentRefunded(Subscription $subscription): void
    {
        $subscription->update(['status' => SubscriptionStatus::Expired]);

        $this->notifyUser(
            $subscription->user,
            fn (Notification $n) => $n
                ->title('Pagamento reembolsado')
                ->body('Seu pagamento foi reembolsado e o acesso foi suspenso.')
                ->danger(),
        );

        Log::info("[{$this->gateway}] Pagamento reembolsado para user #{$subscription->user_id}");
    }

    private function onSubscriptionCancelled(Subscription $subscription): void
    {
        // Carência: se ainda há período pago, mantém o acesso até o fim dele.
        $hasPaidPeriod = $subscription->current_period_ends_at?->isFuture() ?? false;

        $subscription->update([
            'status' => $hasPaidPeriod ? SubscriptionStatus::Canceled : SubscriptionStatus::Expired,
            'canceled_at' => now(),
        ]);

        $this->notifyUser(
            $subscription->user,
            fn (Notification $n) => $n
                ->title('Assinatura cancelada')
                ->body($hasPaidPeriod
                    ? 'Você mantém o acesso até '.$subscription->current_period_ends_at->format('d/m/Y').'.'
                    : 'Sua assinatura foi cancelada.')
                ->warning(),
        );

        Log::info("[{$this->gateway}] Assinatura cancelada para user #{$subscription->user_id}");
    }

    /**
     * @param  callable(Notification): Notification  $configure
     */
    private function notifyUser(?User $user, callable $configure): void
    {
        if ($user === null) {
            return;
        }

        $configure(Notification::make())->sendToDatabase($user);
    }

    private function markFailed(?WebhookLog $log, string $error): void
    {
        $log?->update([
            'status' => 'failed',
            'error' => $error,
        ]);

        Log::warning("[{$this->gateway}] Webhook falhou: {$error}", [
            'log_id' => $this->webhookLogId,
        ]);
    }
}
