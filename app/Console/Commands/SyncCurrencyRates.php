<?php

namespace App\Console\Commands;

use App\Models\CurrencyRate;
use App\Services\CurrencyConverter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncCurrencyRates extends Command
{
    protected $signature = 'marketing:sync-currencies
                            {--period=10 : Quantos anos para trás importar}';

    protected $description = 'Sincroniza as cotações de câmbio (PTAX venda do Banco Central) para currency_rates';

    /** Código SGS => moeda (taxas de venda, diárias). */
    private const SERIES = [
        1 => 'USD',
        21619 => 'EUR',
    ];

    public function handle(): int
    {
        $period = max(1, (int) $this->option('period'));
        $start = now()->subYears($period)->format('d/m/Y');
        $end = now()->format('d/m/Y');

        foreach (self::SERIES as $seriesCode => $currency) {
            try {
                $response = Http::timeout(60)
                    ->retry(3, 1000)
                    ->get(
                        "https://api.bcb.gov.br/dados/serie/bcdata.sgs.{$seriesCode}/dados",
                        [
                            'formato' => 'json',
                            'dataInicial' => $start,
                            'dataFinal' => $end,
                        ]
                    );

                if (! $response->successful()) {
                    $this->warn("Não foi possível importar {$currency}.");

                    continue;
                }

                $rows = [];

                foreach ($response->json() as $item) {
                    $rows[] = [
                        'currency' => $currency,
                        'date' => Carbon::createFromFormat('d/m/Y', $item['data'])->toDateString(),
                        'rate' => (float) str_replace(',', '.', $item['valor']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    CurrencyRate::upsert($chunk, ['currency', 'date'], ['rate', 'updated_at']);
                }

                $this->info("{$currency}: ".count($rows).' cotações sincronizadas.');
                usleep(250000);
            } catch (\Throwable $e) {
                $this->error("Erro ao importar {$currency}: {$e->getMessage()}");
            }
        }

        CurrencyConverter::flush();

        return self::SUCCESS;
    }
}
