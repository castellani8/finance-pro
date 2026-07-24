<?php

namespace App\Providers;

use App\Ai\ChartRegistry;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Filament\Auth\Notifications as FilamentAuthNotifications;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Escopo de request: a tool GerarGrafico empilha e o MilhaChat drena
        // a MESMA instância dentro da mesma requisição.
        $this->app->scoped(ChartRegistry::class);

        // O Filament resolve os e-mails de autenticação via container; os
        // binds trocam os templates padrão pelas versões com a marca.
        $this->app->bind(FilamentAuthNotifications\VerifyEmail::class, \App\Notifications\Auth\VerifyEmail::class);
        $this->app->bind(FilamentAuthNotifications\ResetPassword::class, \App\Notifications\Auth\ResetPassword::class);
        $this->app->bind(FilamentAuthNotifications\VerifyEmailChange::class, \App\Notifications\Auth\VerifyEmailChange::class);
        $this->app->bind(FilamentAuthNotifications\NoticeOfEmailChangeRequest::class, \App\Notifications\Auth\NoticeOfEmailChangeRequest::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Transaction::observe(TransactionObserver::class);
    }
}
