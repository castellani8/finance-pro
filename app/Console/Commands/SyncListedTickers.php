<?php

namespace App\Console\Commands;

use App\Models\B3ListedTicker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncListedTickers extends Command
{
    protected $signature = 'marketing:sync-tickers';

    protected $description = 'Sincroniza o catálogo de tickers negociáveis da B3 (brapi) para b3_listed_tickers';

    public function handle(): int
    {
        $response = Http::timeout(60)
            ->retry(3, 1000)
            ->withToken(config('services.brapi.token'))
            ->get('https://brapi.dev/api/quote/list');

        if (! $response->successful()) {
            $this->error('Não foi possível obter a lista de tickers da brapi.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($response->json('stocks', []) as $item) {
            $ticker = trim((string) ($item['stock'] ?? ''));

            if ($ticker === '') {
                continue;
            }

            $rows[] = [
                'ticker' => $ticker,
                'name' => $item['name'] ?? null,
                'asset_kind' => $item['type'] ?? null,
                'sector' => $item['sector'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            $this->warn('A brapi não retornou nenhum ticker; catálogo mantido como está.');

            return self::SUCCESS;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            B3ListedTicker::upsert($chunk, ['ticker'], ['name', 'asset_kind', 'sector', 'updated_at']);
        }

        $this->info(count($rows).' tickers sincronizados.');

        return self::SUCCESS;
    }
}
