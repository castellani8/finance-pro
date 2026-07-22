<?php

namespace App\Providers;

use App\Ai\ChartRegistry;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Transaction::observe(TransactionObserver::class);
    }
}
