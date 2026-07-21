<?php

namespace App\Services\Payments;

use App\Services\Asaas\Asaas;
use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Gateways\AsaasGateway;
use Illuminate\Support\Manager;

/**
 * Resolve o driver de pagamento ativo a partir de config('subscription.gateway').
 *
 * Para adicionar um novo provedor (ex.: Pagar.me): crie um driver que implemente
 * PaymentGateway e adicione um método createPagarmeDriver() aqui.
 */
class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('subscription.gateway', 'asaas');
    }

    public function createAsaasDriver(): PaymentGateway
    {
        return new AsaasGateway($this->container->make(Asaas::class));
    }
}
