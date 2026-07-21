<?php

namespace App\Services\Payments\Data;

/**
 * Dados do pagador necessários para criar/identificar o cliente no gateway.
 *
 * Exigidos em qualquer forma de pagamento (PIX, cartão, boleto). Os campos de
 * endereço só são usados na análise antifraude do cartão.
 */
class PayerDetails
{
    public function __construct(
        public string $name,
        public string $email,
        public string $cpfCnpj,
        public string $phone,
        public ?string $postalCode = null,
        public ?string $addressNumber = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['holder_name']),
            email: $data['holder_email'],
            cpfCnpj: preg_replace('/\D/', '', $data['holder_cpf_cnpj']),
            phone: preg_replace('/\D/', '', $data['holder_phone']),
            postalCode: isset($data['holder_postal_code'])
                ? preg_replace('/\D/', '', $data['holder_postal_code'])
                : null,
            addressNumber: $data['holder_address_number'] ?? null,
        );
    }
}
