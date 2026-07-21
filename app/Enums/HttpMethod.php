<?php

namespace App\Enums;

/**
 * Verbos HTTP aceitos pelo cliente de integrações (App\Services\Asaas\Asaas).
 * O value é o nome do método correspondente no Http client do Laravel.
 */
enum HttpMethod: string
{
    case GET = 'get';
    case POST = 'post';
    case PUT = 'put';
    case DELETE = 'delete';
}
