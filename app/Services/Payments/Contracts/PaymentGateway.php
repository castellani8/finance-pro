<?php

namespace App\Services\Payments\Contracts;

use App\Exceptions\PaymentGatewayException;
use App\Models\User;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Data\PayerDetails;
use App\Services\Payments\Data\Plan;
use App\Services\Payments\Data\SubscriptionResult;
use App\Services\Payments\Data\WebhookEvent;
use App\Services\Payments\Enums\BillingType;
use Illuminate\Http\Request;

/**
 * Contrato que todo gateway de pagamento (Asaas, Pagar.me, ...) deve cumprir
 * para que a aplicação cobre assinaturas do SaaS sem conhecer o provedor.
 */
interface PaymentGateway
{
    /**
     * Identificador do driver (ex.: "asaas"). Gravado em `subscriptions.gateway`.
     */
    public function name(): string;

    /**
     * Cria (ou reaproveita) o cliente e abre uma assinatura recorrente.
     *
     * No cartão a cobrança é processada na hora; em PIX/boleto o gateway apenas
     * gera a primeira cobrança (o resultado traz a invoiceUrl para pagamento) e
     * o acesso só é liberado quando o webhook confirmar o pagamento.
     *
     * @throws PaymentGatewayException
     */
    public function subscribe(
        User $user,
        Plan $plan,
        BillingType $billingType,
        PayerDetails $payer,
        ?CardDetails $card = null,
        ?string $remoteIp = null,
    ): SubscriptionResult;

    /**
     * Cancela uma assinatura no provedor.
     *
     * @throws PaymentGatewayException
     */
    public function cancelSubscription(string $subscriptionId): void;

    /**
     * Valida a autenticidade da requisição de webhook (assinatura/token).
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Normaliza o payload cru do provedor em um WebhookEvent.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): WebhookEvent;
}
