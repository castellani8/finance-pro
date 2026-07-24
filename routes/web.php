<?php

use App\Http\Controllers\CartaPatrimonioController;
use App\Http\Controllers\ContadorReportController;
use App\Http\Controllers\ConviteController;
use App\Http\Controllers\EmailPixelController;
use App\Http\Controllers\MarketingEmailController;
use App\Http\Controllers\Webhooks\SubscriptionWebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing.index')->name('landing');

Route::view('/privacidade', 'legal.privacidade')->name('legal.privacidade');

// Opt-out/opt-in de e-mail marketing via link assinado (sem login). O POST
// atende o one-click unsubscribe (RFC 8058) do header List-Unsubscribe.
Route::match(['get', 'post'], '/email/descadastrar/{user}', [MarketingEmailController::class, 'unsubscribe'])
    ->middleware('signed')
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('marketing.unsubscribe');

Route::get('/email/reativar/{user}', [MarketingEmailController::class, 'resubscribe'])
    ->middleware('signed')
    ->name('marketing.resubscribe');

// Pixel de rastreio de abertura dos e-mails logados em email_logs.
Route::get('/email/pixel/{emailLog}', [EmailPixelController::class, 'read'])
    ->middleware('signed')
    ->name('email-logs.read');

// Webhooks de gateways de pagamento (Asaas etc.) — autenticidade validada
// pelo driver via token, não por sessão/CSRF.
Route::post('/webhooks/{gateway}', [SubscriptionWebhookController::class, 'handle'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('webhooks.subscription');

// Relatório de IR somente-leitura para o contador — link assinado temporário
// gerado pelo dono da carteira na página Relatório IR do painel.
Route::get('/r/ir/{tenant:uuid}/{year}', [ContadorReportController::class, 'show'])
    ->middleware('signed')
    ->whereNumber('year')
    ->name('contador.ir');

// Convite para participar de uma carteira (modo família): o GET mostra o
// convite; o POST aceita (exige usuário logado no painel).
Route::get('/convite/{token}', [ConviteController::class, 'show'])->name('convite.aceitar');
Route::post('/convite/{token}', [ConviteController::class, 'accept'])->name('convite.confirmar');

// Carta de patrimônio (documento de sucessão, versão para impressão) —
// restrita a usuários autenticados que pertencem ao tenant.
Route::get('/carta-patrimonio/{tenant:uuid}', [CartaPatrimonioController::class, 'show'])
    ->middleware('auth')
    ->name('carta.patrimonio');
