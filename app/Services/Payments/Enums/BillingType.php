<?php

namespace App\Services\Payments\Enums;

/**
 * Forma de pagamento, agnóstica ao gateway.
 */
enum BillingType: string
{
    case CREDIT_CARD = 'CREDIT_CARD';

    case PIX = 'PIX';

    case BOLETO = 'BOLETO';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_CARD => 'Cartão de crédito',
            self::PIX => 'PIX',
            self::BOLETO => 'Boleto',
        };
    }
}
