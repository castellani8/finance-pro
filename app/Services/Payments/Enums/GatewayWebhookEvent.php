<?php

namespace App\Services\Payments\Enums;

/**
 * Eventos de webhook normalizados.
 *
 * Cada driver mapeia os eventos crus do seu provedor para um destes casos,
 * de modo que o processamento (ProcessSubscriptionWebhookJob) seja idêntico
 * independentemente do gateway.
 */
enum GatewayWebhookEvent: string
{
    case PAYMENT_CREATED = 'payment_created';

    case PAYMENT_CONFIRMED = 'payment_confirmed';

    case PAYMENT_OVERDUE = 'payment_overdue';

    case PAYMENT_REFUNDED = 'payment_refunded';

    case SUBSCRIPTION_CANCELLED = 'subscription_cancelled';

    case UNKNOWN = 'unknown';
}
