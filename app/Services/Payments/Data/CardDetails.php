<?php

namespace App\Services\Payments\Data;

/**
 * Dados do cartão para checkout transparente.
 *
 * É deliberadamente neutro: cada driver converte para o formato do seu provedor.
 * Nunca persista esta estrutura — ela trafega apenas em memória durante a cobrança.
 * Os dados do titular/pagador ficam em {@see PayerDetails}.
 */
class CardDetails
{
    public function __construct(
        public string $holderName,
        public string $number,
        public string $expiryMonth,
        public string $expiryYear,
        public string $cvv,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            holderName: trim($data['holder_name']),
            number: preg_replace('/\D/', '', $data['number']),
            expiryMonth: $data['expiry_month'],
            expiryYear: self::normalizeYear($data['expiry_year']),
            cvv: $data['cvv'],
        );
    }

    private static function normalizeYear(string $year): string
    {
        $year = preg_replace('/\D/', '', $year);

        return strlen($year) === 2 ? '20'.$year : $year;
    }
}
