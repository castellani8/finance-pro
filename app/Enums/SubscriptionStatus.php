<?php

namespace App\Enums;

/**
 * Ciclo de vida da assinatura. Os valores foram pensados para mapear
 * diretamente os status de cobrança do Asaas quando a integração chegar:
 * trial local → active/past_due via webhook → canceled/expired.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Período de teste',
            self::Active => 'Ativa',
            self::PastDue => 'Pagamento pendente',
            self::Canceled => 'Cancelada',
            self::Expired => 'Expirada',
        };
    }

    /** Cor de badge no padrão do Filament. */
    public function color(): string
    {
        return match ($this) {
            self::Trialing => 'info',
            self::Active => 'success',
            self::PastDue => 'warning',
            self::Canceled, self::Expired => 'danger',
        };
    }
}
