<?php

namespace App\Services\Asaas\Endpoints;

use App\Enums\HttpMethod;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

trait AsaasCustomer
{
    public function storeCustomer(
        string $name,
        string $email,
        string $cpfCnpj,
        ?string $phone = null,
        ?string $mobilePhone = null,
        ?string $postalCode = null,
        ?string $address = null,
        ?string $addressNumber = null,
        ?string $complement = null,
        ?string $province = null,
        ?string $city = null,
        ?string $state = null,
        ?string $country = null,
        ?string $externalReference = null,
        ?bool $notificationDisabled = true,
        ?string $additionalEmails = null,
        ?string $municipalInscription = null,
        ?string $stateInscription = null,
        ?string $observations = null
    ): array {
        $params = compact('name', 'email', 'cpfCnpj', 'phone', 'mobilePhone', 'postalCode', 'address', 'addressNumber', 'complement', 'province', 'city', 'state', 'country', 'externalReference', 'notificationDisabled', 'additionalEmails', 'municipalInscription', 'stateInscription', 'observations');

        return $this->perform(
            method: HttpMethod::POST,
            endpoint: 'customers',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function findCustomer(string $id): array
    {
        return $this->perform(
            method: HttpMethod::GET,
            endpoint: "customers/{$id}"
        );
    }

    public function updateCustomer(
        string $id,
        ?string $name = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $mobilePhone = null,
        ?string $cpfCnpj = null,
        ?string $postalCode = null,
        ?string $address = null,
        ?string $addressNumber = null,
        ?string $complement = null,
        ?string $province = null,
        ?string $city = null,
        ?string $state = null,
        ?string $country = null,
        ?string $externalReference = null,
        ?bool $notificationDisabled = null,
        ?string $additionalEmails = null,
        ?string $municipalInscription = null,
        ?string $stateInscription = null,
        ?string $observations = null
    ): array {
        $params = compact('name', 'email', 'phone', 'mobilePhone', 'cpfCnpj', 'postalCode', 'address', 'addressNumber', 'complement', 'province', 'city', 'state', 'country', 'externalReference', 'notificationDisabled', 'additionalEmails', 'municipalInscription', 'stateInscription', 'observations');

        return $this->perform(
            method: HttpMethod::PUT,
            endpoint: "customers/{$id}",
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function deleteCustomer(string $id): array
    {
        return $this->perform(
            method: HttpMethod::DELETE,
            endpoint: "customers/{$id}"
        );
    }

    public function listCustomers(
        ?int $limit = null,
        ?int $offset = null,
        ?string $name = null,
        ?string $email = null,
        ?string $cpfCnpj = null,
        ?string $groupName = null,
        ?string $externalReference = null
    ): array {
        $params = compact('limit', 'offset', 'name', 'email', 'cpfCnpj', 'groupName', 'externalReference');

        return $this->perform(
            method: HttpMethod::GET,
            endpoint: 'customers',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function restoreCustomer(string $id): array
    {
        return $this->perform(
            method: HttpMethod::POST,
            endpoint: "customers/{$id}/restore"
        );
    }

    /**
     * Busca um customer por CPF/CNPJ para evitar duplicatas
     */
    public function findCustomerByCpfCnpj(string $cpfCnpj): array
    {
        return $this->listCustomers(cpfCnpj: $cpfCnpj);
    }

    /**
     * Busca ou cria um customer por CPF/CNPJ
     * Útil para evitar duplicatas conforme recomendado na documentação
     */
    public function findOrCreateCustomerByCpfCnpj(
        string $cpfCnpj,
        string $name,
        string $email,
        ?string $phone = null,
        ?string $mobilePhone = null,
        ?string $postalCode = null,
        ?string $address = null,
        ?string $addressNumber = null,
        ?string $complement = null,
        ?string $province = null,
        ?string $city = null,
        ?string $state = null,
        ?string $country = null,
        ?string $externalReference = null,
        ?bool $notificationDisabled = true,
        ?string $additionalEmails = null,
        ?string $municipalInscription = null,
        ?string $stateInscription = null,
        ?string $observations = null
    ): array {
        $existingCustomer = $this->findCustomerByCpfCnpj($cpfCnpj);

        if (! empty($existingCustomer['body']['data'][0])) {
            return [
                'body' => $existingCustomer['body']['data'][0],
                'status_code' => HttpResponse::HTTP_OK,
            ];
        }

        return $this->storeCustomer(
            name: $name,
            email: $email,
            phone: $phone,
            mobilePhone: $mobilePhone,
            cpfCnpj: $cpfCnpj,
            postalCode: $postalCode,
            address: $address,
            addressNumber: $addressNumber,
            complement: $complement,
            province: $province,
            city: $city,
            state: $state,
            country: $country,
            externalReference: $externalReference,
            notificationDisabled: $notificationDisabled,
            additionalEmails: $additionalEmails,
            municipalInscription: $municipalInscription,
            stateInscription: $stateInscription,
            observations: $observations
        );
    }
}
