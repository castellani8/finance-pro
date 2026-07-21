<?php

use App\Http\Controllers\MarketingEmailController;
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
