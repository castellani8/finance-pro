<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contato
    |--------------------------------------------------------------------------
    |
    | Dados de contato exibidos na landing page (rodapé e seções de suporte).
    | Sobrescreva pelo .env sem tocar no código.
    |
    */

    'contact' => [
        'email' => env('LANDING_CONTACT_EMAIL', 'contato@miliainvest.com'),
        'whatsapp' => env('LANDING_CONTACT_WHATSAPP', '(11) 92085-2848'),
        'whatsapp_url' => env('LANDING_CONTACT_WHATSAPP_URL', 'https://wa.me/5511920852848'),
        'instagram_url' => env('LANDING_SOCIAL_INSTAGRAM'),
        'linkedin_url' => env('LANDING_SOCIAL_LINKEDIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plano
    |--------------------------------------------------------------------------
    */

    'plan' => [
        'price' => env('LANDING_PLAN_PRICE', '19,90'),
        // Preço "de" exibido riscado na landing (promoção). Deixe vazio para ocultar.
        'original_price' => env('LANDING_PLAN_ORIGINAL_PRICE', '39,90'),
        'trial_days' => (int) env('LANDING_PLAN_TRIAL_DAYS', 15),
    ],

];
