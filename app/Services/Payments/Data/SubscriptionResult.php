<?php

namespace App\Services\Payments\Data;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;

/**
 * Resultado normalizado da criação de uma assinatura em um gateway.
 */
class SubscriptionResult
{
    /**
     * @param  array<string, mixed>  $raw  Resposta crua do provedor (para auditoria/log).
     */
    public function __construct(
        public string $gateway,
        public string $customerId,
        public string $subscriptionId,
        public SubscriptionStatus $status,
        public ?CarbonInterface $nextDueDate = null,
        public ?string $invoiceUrl = null,
        public array $raw = [],
    ) {}
}
