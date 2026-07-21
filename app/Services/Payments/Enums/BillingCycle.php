<?php

namespace App\Services\Payments\Enums;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Ciclo de cobrança, agnóstico ao gateway. Cada driver é responsável por
 * traduzir estes valores para o formato esperado pelo seu provedor.
 */
enum BillingCycle: string
{
    case MONTHLY = 'MONTHLY';

    case YEARLY = 'YEARLY';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensal',
            self::YEARLY => 'Anual',
        };
    }

    /**
     * Avança uma data em um ciclo (usado para calcular o fim do período pago).
     */
    public function addTo(CarbonInterface $date): Carbon
    {
        return match ($this) {
            self::MONTHLY => $date->copy()->addMonth(),
            self::YEARLY => $date->copy()->addYear(),
        };
    }
}
