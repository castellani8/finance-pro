<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway de pagamento
    |--------------------------------------------------------------------------
    |
    | Driver ativo do PaymentGatewayManager. Para adicionar um provedor novo,
    | implemente PaymentGateway e registre um create{Nome}Driver no manager.
    |
    */

    'gateway' => env('SUBSCRIPTION_GATEWAY', 'asaas'),

    /*
    |--------------------------------------------------------------------------
    | Planos
    |--------------------------------------------------------------------------
    |
    | O preço acompanha o mesmo env da landing (LANDING_PLAN_PRICE) para os
    | dois nunca divergirem.
    |
    */

    'default_plan' => 'completo',

    'plans' => [
        'completo' => [
            'name' => 'Milia Invest completo',
            'price' => (float) str_replace(',', '.', env('LANDING_PLAN_PRICE', '19,90')),
            'cycle' => 'MONTHLY',
            'description' => 'Todos os recursos da Milia Invest, sem limites.',
        ],
    ],

];
