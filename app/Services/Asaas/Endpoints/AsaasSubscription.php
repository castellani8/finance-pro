<?php

namespace App\Services\Asaas\Endpoints;

use App\Enums\HttpMethod;
use Carbon\Carbon;

trait AsaasSubscription
{
    public function storeSubscription(
        string $customer,
        Carbon $nextDueDate,
        float $value,
        string $cycle,
        string $billingType = 'CREDIT_CARD',
        ?string $description = null,
        ?string $endDate = null,
        ?int $maxPayments = null,
        ?string $externalReference = null,
        ?float $discount = null,
        ?float $interest = null,
        ?float $fine = null,
        ?bool $updatePendingPayments = null,
        ?array $creditCard = null,
        ?array $creditCardHolderInfo = null,
        ?string $creditCardToken = null,
        ?string $remoteIp = null,
    ): array {
        $params = compact('customer', 'billingType', 'value', 'cycle', 'description', 'endDate', 'maxPayments', 'externalReference', 'discount', 'interest', 'fine', 'updatePendingPayments', 'creditCard', 'creditCardHolderInfo', 'creditCardToken', 'remoteIp') + [
            'nextDueDate' => $nextDueDate->format('Y-m-d'),
        ];

        return $this->perform(
            method: HttpMethod::POST,
            endpoint: 'subscriptions',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function storeSubscriptionWithCreditCardToken(
        string $customer,
        Carbon $nextDueDate,
        float $value,
        string $cycle,
        string $creditCardToken,
        ?string $description = null,
        ?string $endDate = null,
        ?int $maxPayments = null,
        ?string $externalReference = null,
        ?float $discount = null,
        ?float $interest = null,
        ?float $fine = null,
        ?string $remoteIp = null,
    ): array {
        return $this->storeSubscription(
            customer: $customer,
            billingType: 'CREDIT_CARD',
            nextDueDate: $nextDueDate,
            value: $value,
            cycle: $cycle,
            description: $description,
            endDate: $endDate,
            maxPayments: $maxPayments,
            externalReference: $externalReference,
            discount: $discount,
            interest: $interest,
            fine: $fine,
            creditCardToken: $creditCardToken,
            remoteIp: $remoteIp,
        );
    }

    public function findSubscription(string $id): array
    {
        return $this->perform(
            method: HttpMethod::GET,
            endpoint: "subscriptions/{$id}"
        );
    }

    public function updateSubscription(
        string $id,
        ?string $customer = null,
        ?string $billingType = null,
        ?Carbon $nextDueDate = null,
        ?float $value = null,
        ?string $cycle = null,
        ?string $description = null,
        ?string $endDate = null,
        ?int $maxPayments = null,
        ?string $externalReference = null,
        ?float $discount = null,
        ?float $interest = null,
        ?float $fine = null,
        ?bool $updatePendingPayments = null,
    ): array {
        $params = [
            'customer' => $customer,
            'billingType' => $billingType,
            'nextDueDate' => $nextDueDate?->format('Y-m-d'),
            'value' => $value,
            'cycle' => $cycle,
            'description' => $description,
            'endDate' => $endDate,
            'maxPayments' => $maxPayments,
            'externalReference' => $externalReference,
            'discount' => $discount,
            'interest' => $interest,
            'fine' => $fine,
            'updatePendingPayments' => $updatePendingPayments,
        ];

        return $this->perform(
            method: HttpMethod::PUT,
            endpoint: "subscriptions/{$id}",
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function inactiveSubscription(string $id): array
    {
        return $this->perform(
            method: HttpMethod::PUT,
            endpoint: "subscriptions/{$id}",
            params: [
                'status' => 'INACTIVE',
            ]
        );
    }

    public function deleteSubscription(string $id): array
    {
        return $this->perform(
            method: HttpMethod::DELETE,
            endpoint: "subscriptions/{$id}"
        );
    }

    public function listSubscriptions(?int $limit = null, ?int $offset = null, ?string $customer = null): array
    {
        $params = compact('limit', 'offset', 'customer');

        return $this->perform(
            method: HttpMethod::GET,
            endpoint: 'subscriptions',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function getSubscriptionPayments(string $id, ?int $limit = null, ?int $offset = null): array
    {
        $params = compact('limit', 'offset');

        return $this->perform(
            method: HttpMethod::GET,
            endpoint: "subscriptions/{$id}/payments",
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }
}
