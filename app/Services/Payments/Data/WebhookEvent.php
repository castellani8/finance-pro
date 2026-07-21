<?php

namespace App\Services\Payments\Data;

use App\Services\Payments\Enums\GatewayWebhookEvent;
use Carbon\CarbonInterface;

/**
 * Evento de webhook normalizado, independente do provedor.
 */
class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public GatewayWebhookEvent $type,
        public ?string $externalReference = null,
        public ?string $customerId = null,
        public ?string $subscriptionId = null,
        public ?float $value = null,
        public ?CarbonInterface $dueDate = null,
        public array $raw = [],
    ) {}

    /**
     * O externalReference é gravado como "user_{id}" ao criar a assinatura.
     * Devolve o id do usuário quando possível.
     */
    public function userId(): ?int
    {
        if (! $this->externalReference || ! str_starts_with($this->externalReference, 'user_')) {
            return null;
        }

        return (int) str_replace('user_', '', $this->externalReference);
    }
}
