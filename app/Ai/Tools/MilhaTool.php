<?php

namespace App\Ai\Tools;

use App\Models\Tenant;
use Laravel\Ai\Contracts\Tool;

/**
 * Base das tools da Milha. Toda tool nasce amarrada a um tenant — o modelo
 * nunca escolhe de quem são os dados — e devolve JSON legível para o LLM.
 */
abstract class MilhaTool implements Tool
{
    public function __construct(protected readonly Tenant $tenant) {}

    /** @param array<string, mixed> $payload */
    protected function json(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function money(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
