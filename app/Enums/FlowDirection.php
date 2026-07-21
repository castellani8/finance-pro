<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Sentido do fluxo de um lançamento: entrada (Crédito) ou saída (Débito).
 * Centraliza o parsing do texto da B3 e o sentido padrão por tipo, que antes
 * estavam espalhados entre Transaction, generator e formulários.
 */
enum FlowDirection: string
{
    case Credit = 'Credito';
    case Debit = 'Debito';

    /** Resolve a partir do texto salvo (tolerante a acentos), com fallback pelo tipo. */
    public static function resolve(?string $direction, string $type): self
    {
        $normalized = Str::upper(Str::ascii((string) $direction));

        return match ($normalized) {
            'CREDITO' => self::Credit,
            'DEBITO' => self::Debit,
            default => self::defaultForType($type),
        };
    }

    /** Sentido natural de um lançamento manual do tipo (venda e despesa saem). */
    public static function defaultForType(string $type): self
    {
        return in_array($type, ['SELL', 'EXPENSE'], true) ? self::Debit : self::Credit;
    }

    public function isCredit(): bool
    {
        return $this === self::Credit;
    }

    /** +1 para crédito, -1 para débito. */
    public function sign(): int
    {
        return $this->isCredit() ? 1 : -1;
    }
}
