<?php

namespace App\Exceptions;

/**
 * Erro retornado pela API do Asaas (a mensagem carrega o corpo da resposta).
 */
class AsaasException extends PaymentGatewayException
{
    /** Primeira descrição de erro legível do corpo da resposta, se houver. */
    public function friendlyMessage(): string
    {
        $body = json_decode($this->getMessage(), true);

        return $body['errors'][0]['description']
            ?? 'Não foi possível concluir a operação com o gateway de pagamento.';
    }
}
