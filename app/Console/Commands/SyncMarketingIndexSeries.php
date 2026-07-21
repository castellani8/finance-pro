<?php

namespace App\Console\Commands;

use App\Models\MarketingIndexSeries;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncMarketingIndexSeries extends Command
{
    protected $signature = 'marketing:sync-indices
                            {--period=2 : Quantos anos para trás importar}';

    protected $description = 'Sincroniza os principais índices econômicos do SGS (Banco Central)';

    /**
     * Código SGS => Nome do índice.
     *
     * Só entram séries que são TAXAS do período (% ao dia para SELIC/CDI,
     * % ao mês para IPCA/IGP-M), pois o IndexAccumulator compõe registro a
     * registro. Séries de pontos/nível (ex: IBOV) não pertencem a esta tabela.
     */
    private const INDICES = [
        11 => 'SELIC',
        12 => 'CDI',
        433 => 'IPCA',
        189 => 'IGP-M',
    ];

    public function handle(): int
    {
        $period = max(1, (int) $this->option('period'));

        $start = now()->subYears($period)->format('d/m/Y');
        $end = now()->format('d/m/Y');

        $this->info("Importando índices dos últimos {$period} ano(s)...");

        $bar = $this->output->createProgressBar(count(self::INDICES));
        $bar->start();

        foreach (self::INDICES as $seriesCode => $indexCode) {

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
                    $this->newLine();
                    $this->warn("Não foi possível importar {$indexCode}.");

                    $bar->advance();

                    continue;
                }

                $rows = [];

                foreach ($response->json() as $item) {

                    $value = (float) str_replace(',', '.', $item['valor']);

                    $rows[] = [
                        'index_code' => $indexCode,
                        'date' => Carbon::createFromFormat('d/m/Y', $item['data'])->toDateString(),

                        // Taxa do período em % (dia para SELIC/CDI, mês para IPCA/IGP-M).
                        'daily_factor' => $value,
                        'annual_rate' => null,

                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($rows)) {
                    MarketingIndexSeries::upsert(
                        $rows,
                        ['index_code', 'date'],
                        ['daily_factor', 'annual_rate', 'updated_at']
                    );
                }

                $this->line(" ✔ {$indexCode} (".count($rows).' registros)');

                // evita bombardear a API
                usleep(250000);

            } catch (\Throwable $e) {

                $this->newLine();
                $this->error("Erro ao importar {$indexCode}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();

        $this->newLine(2);
        $this->info('Importação concluída com sucesso.');

        return self::SUCCESS;
    }
}
