<?php

namespace App\Services\Asaas\Objects;

class CreditCardHolderInfo
{
    public function __construct(
        public string $name,
        public string $email,
        public string $cpfCnpj,
        public string $postalCode,
        public string $addressNumber,
        public string $phone,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'cpfCnpj' => $this->cpfCnpj,
            'postalCode' => $this->postalCode,
            'addressNumber' => $this->addressNumber,
            'phone' => $this->phone,
        ];
    }

    public static function make(
        string $name,
        string $email,
        string $cpfCnpj,
        string $postalCode,
        string $addressNumber,
        ?string $addressComplement,
        string $phone,
        string $mobilePhone
    ): self {
        return new self(
            $name,
            $email,
            $cpfCnpj,
            $postalCode,
            $addressNumber,
            $addressComplement,
            $phone,
            $mobilePhone
        );
    }
}
