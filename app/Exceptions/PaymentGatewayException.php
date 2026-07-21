<?php

namespace App\Exceptions;

use Exception;

/**
 * Falha genérica de gateway de pagamento — capture esta na aplicação para
 * tratar erros de cobrança sem conhecer o provedor.
 */
class PaymentGatewayException extends Exception {}
