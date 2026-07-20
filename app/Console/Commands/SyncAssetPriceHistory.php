<?php

namespace App\Console\Commands;

use App\Models\AssetPriceHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncAssetPriceHistory extends Command
{
    protected $signature = 'marketing:sync-assets
                            {--period=2 : Quantos anos importar}';

    protected $description = 'Importa histórico de ativos do Yahoo Finance';

    private const ASSETS = [
        '^BVSP',

        'PETR4.SA',
        'VALE3.SA',
        'ITUB4.SA',
        'BBAS3.SA',
        'BBDC4.SA',
        'ABEV3.SA',
        'WEGE3.SA',
        'ITSA4.SA',
        'MGLU3.SA',
    ];

    public function handle(): int
    {
        $period = max(1, (int) $this->option('period'));

        $this->info("Importando ativos dos últimos {$period} ano(s)...");

        $bar = $this->output->createProgressBar(count(self::ASSETS));
        $bar->start();

        foreach ($this->getTickers() as $ticker) {

            try {

                $response = Http::timeout(60)
                    ->retry(3, 1000)
                    ->get(
                        "https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}",
                        [
                            'range' => "{$period}y",
                            'interval' => '1d',
                            'includePrePost' => 'false',
                            'events' => 'div,splits',
                        ]
                    );

                if (! $response->successful()) {
                    throw new \Exception($response->body());
                }

                $result = data_get(
                    $response->json(),
                    'chart.result.0'
                );

                if (!$result) {

                    $this->newLine();
                    $this->warn("Sem dados para {$ticker}");

                    $bar->advance();

                    continue;
                }

                $timestamps = data_get($result, 'timestamp', []);

                $quote = data_get($result, 'indicators.quote.0', []);

                $opens  = $quote['open']  ?? [];
                $closes = $quote['close'] ?? [];
                $highs  = $quote['high']  ?? [];
                $lows   = $quote['low']   ?? [];

                $rows = [];

                foreach ($timestamps as $i => $timestamp) {

                    if (
                        !isset($opens[$i]) ||
                        !isset($highs[$i]) ||
                        !isset($lows[$i]) ||
                        !isset($closes[$i])
                    ) {
                        continue;
                    }

                    $rows[] = [

                        'ticker' => str_replace('.SA', '', $ticker),

                        'date' => Carbon::createFromTimestamp($timestamp)
                            ->toDateString(),

                        'price_open' => $opens[$i],
                        'price_close' => $closes[$i],
                        'price_high' => $highs[$i],
                        'price_low' => $lows[$i],

                        'source' => 'yahoo',

                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                AssetPriceHistory::upsert(
                    $rows,
                    ['ticker', 'date', 'source'],
                    [
                        'price_open',
                        'price_close',
                        'price_high',
                        'price_low',
                        'updated_at',
                    ]
                );

                $this->line(" ✔ {$ticker} (" . count($rows) . " registros)");

            } catch (\Throwable $e) {

                $this->newLine();
                $this->error("Erro {$ticker}: {$e->getMessage()}");
            }

            usleep(300000);

            $bar->advance();
        }

        $bar->finish();

        $this->newLine(2);
        $this->info('Importação concluída.');

        return self::SUCCESS;
    }

    private function getTickers(): array
    {
        $response = Http::timeout(30)
            ->withToken(config('services.brapi.token'))
            ->get('https://brapi.dev/api/available');

        if (! $response->successful()) {
            throw new \RuntimeException('Não foi possível obter a lista de ativos.');
        }

        $json = $response->json();

        return collect($json['stocks'] ?? [])
            ->map(fn ($ticker) => "{$ticker}.SA")
            ->push('^BVSP')
            ->push('^IFIX')
            ->unique()
            ->values()
            ->all();
    }
}