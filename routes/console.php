<?php

use App\Jobs\SyncMarketData;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mantém os índices econômicos (CDI, IPCA, SELIC, IGP-M) e os preços dos
// ativos atualizados diariamente, após o fechamento do dia anterior; depois
// fotografa a carteira de cada tenant com os dados frescos. Tudo via fila:
// o scheduler não fica preso em API externa e cada tenant tem retry próprio.
Schedule::job(new SyncMarketData('marketing:sync-indices'))->weekdays()->at('06:00');
Schedule::job(new SyncMarketData('marketing:sync-assets'))->weekdays()->at('06:30');
Schedule::command('portfolio:snapshot')->weekdays()->at('07:30');

// Catálogo de tickers negociáveis muda pouco: uma vez por semana basta.
Schedule::job(new SyncMarketData('marketing:sync-tickers'))->sundays()->at('05:00');

// Contratos recorrentes (aluguéis, assinaturas) viram lançamentos no vencimento.
Schedule::command('ledger:generate-recurring')->dailyAt('00:30');
