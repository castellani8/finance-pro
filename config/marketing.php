<?php

return [

    /*
    |--------------------------------------------------------------------------
    | E-mail marketing (growth)
    |--------------------------------------------------------------------------
    |
    | Campanhas de ciclo de vida enviadas pelo comando marketing:send-campaigns
    | (agendado diariamente) e pelo e-mail de boas-vindas no cadastro. Cada
    | campanha é enviada no máximo uma vez por usuário e respeita o opt-out
    | (users.marketing_emails_unsubscribed_at).
    |
    */

    'enabled' => env('MARKETING_EMAILS_ENABLED', true),

    // Dias de tolerância após a janela ideal de cada campanha: se o usuário
    // ficou fora do ar (ou o cron falhou), ainda enviamos até N dias depois —
    // passado isso, o e-mail perde o contexto e é melhor pular.
    'grace_days' => 2,

];
