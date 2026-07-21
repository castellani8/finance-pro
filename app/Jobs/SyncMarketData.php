<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Roda um dos commands de sincronização de mercado dentro da fila, com retry
 * e sem segurar o scheduler (as APIs externas podem demorar/falhar).
 */
class SyncMarketData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    public int $timeout = 3600;

    /** @param  'marketing:sync-indices'|'marketing:sync-assets'|'marketing:sync-tickers'  $command */
    public function __construct(public string $command) {}

    public function handle(): void
    {
        Artisan::call($this->command);
    }
}
