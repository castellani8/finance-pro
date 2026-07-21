<?php

namespace App\Services\Asaas\Endpoints;

use App\Enums\HttpMethod;
use App\Services\Asaas\Objects\CreditCard;
use App\Services\Asaas\Objects\CreditCardHolderInfo;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * @see https://docs.asaas.com/reference/pay-a-charge-with-credit-card
 */
trait AsaasPayment
{
    public function storePixPayment(
        string $customer,
        float $value,
        Carbon $dueDate,
        ?string $description = null,
        ?string $externalReference = null,
    ): array {
        $payment = $this->storePayment(
            customer: $customer,
            billingType: 'PIX',
            value: $value,
            dueDate: $dueDate,
            description: $description,
            externalReference: $externalReference,
        )['body'];

        $qrCodeInfo = $this->getPaymentQrCode($payment['id'])['body'];

        return [
            'body' => [
                ...$payment,
                'qrCodeInfo' => [
                    'expirationDate' => $qrCodeInfo['expirationDate'],
                    'payload' => $qrCodeInfo['payload'],
                    'encodedImage' => $qrCodeInfo['encodedImage'],
                ],
            ],
            'status_code' => HttpResponse::HTTP_OK,
        ];
    }

    public function storePayment(
        string $customer,
        string $billingType,
        float $value,
        Carbon $dueDate,
        ?string $description = null,
        ?string $externalReference = null,
        ?int $installmentCount = null,
        ?float $installmentValue = null,
    ): array {
        $params = compact('customer', 'billingType', 'value', 'description', 'externalReference', 'installmentCount', 'installmentValue') + [
            'dueDate' => $dueDate->format('Y-m-d'),
        ];

        return $this->perform(
            method: HttpMethod::POST,
            endpoint: 'payments',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function findPayment(string $id): array
    {
        return $this->perform(
            method: HttpMethod::GET,
            endpoint: "payments/{$id}"
        );
    }

    public function updatePayment(
        string $id,
        ?string $customer = null,
        ?string $billingType = null,
        ?float $value = null,
        ?Carbon $dueDate = null,
        ?string $description = null,
        ?string $externalReference = null,
        ?int $installmentCount = null,
        ?float $installmentValue = null,
    ): array {
        $params = compact('customer', 'billingType', 'value', 'description', 'externalReference', 'installmentCount', 'installmentValue') + [
            'dueDate' => $dueDate?->format('Y-m-d'),
        ];

        return $this->perform(
            method: HttpMethod::PUT,
            endpoint: "payments/{$id}",
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function deletePayment(string $id): array
    {
        return $this->perform(
            method: HttpMethod::DELETE,
            endpoint: "payments/{$id}"
        );
    }

    public function listPayments(?int $limit = null, ?int $offset = null, ?string $customer = null): array
    {
        $params = compact('limit', 'offset', 'customer');

        return $this->perform(
            method: HttpMethod::GET,
            endpoint: 'payments',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    /**
     * Obter QR Code para pagamentos via PIX
     *
     * @see https://docs.asaas.com/reference/get-qr-code-for-pix-payments
     */
    public function getPaymentQrCode(string $id): array
    {
        return $this->perform(
            method: HttpMethod::GET,
            endpoint: "payments/{$id}/pixQrCode"
        );
    }

    public function confirmPayment(string $id, ?float $paymentValue = null, ?Carbon $paymentDate = null): array
    {
        $params = [
            'paymentValue' => $paymentValue,
            'paymentDate' => $paymentDate?->format('Y-m-d'),
        ];

        return $this->perform(
            method: HttpMethod::POST,
            endpoint: "payments/{$id}/receiveInCash",
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    /**
     * Tokeniza um cartão de crédito
     */
    public function tokenizeCreditCard(
        string $customer,
        ?CreditCard $creditCard = null,
        ?CreditCardHolderInfo $creditCardHolderInfo = null,
        ?string $remoteIp = null
    ): array {
        $params = [
            'customer' => $customer,
            'creditCard' => $creditCard?->toArray(),
            'creditCardHolderInfo' => $creditCardHolderInfo?->toArray(),
            'remoteIp' => $remoteIp,
        ];

        return $this->perform(
            method: HttpMethod::POST,
            endpoint: 'creditCard/tokenize',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    /**
     * Paga uma cobrança existente com cartão de crédito
     */
    public function payWithCreditCard(
        string $id,
        ?CreditCard $creditCard = null,
        ?CreditCardHolderInfo $creditCardHolderInfo = null,
        ?string $creditCardToken = null,
        ?string $remoteIp = null
    ): array {
        $params = [
            'creditCard' => $creditCard?->toArray(),
            'creditCardHolderInfo' => $creditCardHolderInfo?->toArray(),
            'remoteIp' => $remoteIp,
        ];

        return $this->perform(
            method: HttpMethod::POST,
            endpoint: "payments/{$id}/payWithCreditCard",
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    /**
     * Paga uma cobrança existente com token do cartão de crédito
     */
    public function payWithCreditCardToken(
        string $id,
        string $creditCardToken,
        ?string $remoteIp = null
    ): array {
        $params = [
            'creditCardToken' => $creditCardToken,
            'remoteIp' => $remoteIp,
        ];

        return $this->perform(
            method: HttpMethod::POST,
            endpoint: "payments/{$id}/payWithCreditCard",
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }
}
