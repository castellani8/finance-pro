<?php

namespace App\Services\Payments\Gateways;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Services\Asaas\Asaas;
use App\Services\Asaas\Objects\CreditCard;
use App\Services\Asaas\Objects\CreditCardHolderInfo;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Data\PayerDetails;
use App\Services\Payments\Data\Plan;
use App\Services\Payments\Data\SubscriptionResult;
use App\Services\Payments\Data\WebhookEvent;
use App\Services\Payments\Enums\BillingType;
use App\Services\Payments\Enums\GatewayWebhookEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AsaasGateway implements PaymentGateway
{
    public function __construct(
        private readonly Asaas $asaas,
    ) {}

    public function name(): string
    {
        return 'asaas';
    }

    public function subscribe(
        User $user,
        Plan $plan,
        BillingType $billingType,
        PayerDetails $payer,
        ?CardDetails $card = null,
        ?string $remoteIp = null,
    ): SubscriptionResult {
        $externalReference = "user_{$user->id}";

        $customer = $this->asaas->findOrCreateCustomerByCpfCnpj(
            cpfCnpj: $payer->cpfCnpj,
            name: $payer->name,
            email: $payer->email,
            mobilePhone: $payer->phone,
            postalCode: $payer->postalCode,
            addressNumber: $payer->addressNumber,
            externalReference: $externalReference,
        )['body'];

        $customerId = $customer['id'];

        $creditCard = null;
        $holderInfo = null;

        if ($billingType === BillingType::CREDIT_CARD) {
            if (! $card) {
                throw new \InvalidArgumentException('CardDetails é obrigatório para pagamento com cartão.');
            }

            $creditCard = (new CreditCard(
                holderName: $card->holderName,
                number: $card->number,
                expiryMonth: $card->expiryMonth,
                expiryYear: $card->expiryYear,
                ccv: $card->cvv,
            ))->toArray();

            $holderInfo = (new CreditCardHolderInfo(
                name: $payer->name,
                email: $payer->email,
                cpfCnpj: $payer->cpfCnpj,
                postalCode: $payer->postalCode,
                addressNumber: $payer->addressNumber,
                phone: $payer->phone,
            ))->toArray();
        }

        $subscription = $this->asaas->storeSubscription(
            customer: $customerId,
            nextDueDate: Carbon::today(),
            value: $plan->price,
            cycle: $plan->cycle->value,
            billingType: $billingType->value,
            description: $plan->name,
            externalReference: $externalReference,
            creditCard: $creditCard,
            creditCardHolderInfo: $holderInfo,
            remoteIp: $remoteIp,
        )['body'];

        return new SubscriptionResult(
            gateway: $this->name(),
            customerId: $customerId,
            subscriptionId: $subscription['id'],
            status: $this->mapSubscriptionStatus($subscription['status'] ?? null),
            nextDueDate: isset($subscription['nextDueDate'])
                ? Carbon::parse($subscription['nextDueDate'])
                : null,
            invoiceUrl: $billingType === BillingType::CREDIT_CARD
                ? null
                : $this->firstChargeInvoiceUrl($subscription['id']),
            raw: $subscription,
        );
    }

    /**
     * Recupera a invoiceUrl (checkout hospedado) da primeira cobrança gerada
     * pela assinatura — usada em PIX/boleto, onde o pagamento é manual.
     */
    private function firstChargeInvoiceUrl(string $subscriptionId): ?string
    {
        $payments = $this->asaas->getSubscriptionPayments($subscriptionId, limit: 1)['body']['data'] ?? [];

        return $payments[0]['invoiceUrl'] ?? null;
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $this->asaas->deleteSubscription($subscriptionId);
    }

    public function verifyWebhook(Request $request): bool
    {
        $expected = config('services.asaas.webhook_auth_token');

        if (! $expected) {
            return false;
        }

        return hash_equals($expected, (string) $request->header('asaas-access-token'));
    }

    public function parseWebhook(array $payload): WebhookEvent
    {
        $payment = $payload['payment'] ?? [];
        $subscription = $payload['subscription'] ?? [];

        $type = match ($payload['event'] ?? null) {
            'PAYMENT_CREATED' => GatewayWebhookEvent::PAYMENT_CREATED,
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => GatewayWebhookEvent::PAYMENT_CONFIRMED,
            'PAYMENT_OVERDUE' => GatewayWebhookEvent::PAYMENT_OVERDUE,
            'PAYMENT_REFUNDED' => GatewayWebhookEvent::PAYMENT_REFUNDED,
            'SUBSCRIPTION_DELETED' => GatewayWebhookEvent::SUBSCRIPTION_CANCELLED,
            default => GatewayWebhookEvent::UNKNOWN,
        };

        $dueDate = $payment['dueDate'] ?? null;

        return new WebhookEvent(
            type: $type,
            externalReference: $payment['externalReference'] ?? $subscription['externalReference'] ?? null,
            customerId: $payment['customer'] ?? $subscription['customer'] ?? null,
            subscriptionId: $payment['subscription'] ?? $subscription['id'] ?? null,
            value: isset($payment['value']) ? (float) $payment['value'] : null,
            dueDate: $dueDate ? Carbon::parse($dueDate) : null,
            raw: $payload,
        );
    }

    private function mapSubscriptionStatus(?string $status): SubscriptionStatus
    {
        return match ($status) {
            'EXPIRED' => SubscriptionStatus::Expired,
            'INACTIVE' => SubscriptionStatus::Canceled,
            default => SubscriptionStatus::Active,
        };
    }
}
