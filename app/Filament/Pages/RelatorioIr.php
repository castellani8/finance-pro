<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Services\IrReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class RelatorioIr extends Page
{
    protected string $view = 'filament.pages.relatorio-ir';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Relatório IR';

    protected static ?string $title = 'Relatório para Imposto de Renda';

    protected static ?int $navigationSort = 50;

    public ?int $year = null;

    public function mount(): void
    {
        $options = $this->getYearOptions();
        $previousYear = now()->year - 1;

        $this->year = in_array($previousYear, $options, true)
            ? $previousYear
            : ($options[0] ?? $previousYear);
    }

    /** @return array<int, int> */
    public function getYearOptions(): array
    {
        return Transaction::query()
            ->where('tenant_id', Filament::getTenant()->getKey())
            // cast p/ text: no PostgreSQL substr() não aceita coluna date.
            ->selectRaw('distinct substr(cast(transaction_date as text), 1, 4) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y): int => (int) $y)
            ->all();
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        return app(IrReport::class)->build(Filament::getTenant(), (int) $this->year);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('shareContador')
                ->label('Compartilhar com contador')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('Link para o contador')
                ->modalDescription('Um relatório somente leitura, sem acesso ao restante do painel.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(fn () => view('filament.modals.contador-link', [
                    'year' => (int) $this->year,
                    'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('contador.ir', now()->addDays(45), [
                        'tenant' => Filament::getTenant()->uuid,
                        'year' => (int) $this->year,
                    ]),
                ])),
            Action::make('exportCsv')
                ->label('Baixar CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $report = $this->getReport();

                    return response()->streamDownload(
                        fn () => print (self::toCsv($report)),
                        "relatorio-ir-{$report['year']}.csv",
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    );
                }),
        ];
    }

    /** @param  array<string, mixed>  $report */
    private static function toCsv(array $report): string
    {
        $n = fn (float $v): string => number_format($v, 2, ',', '');
        $lines = ["\u{FEFF}BENS E DIREITOS {$report['year']}"];
        $lines[] = 'Grupo/Codigo;Ticker;Nome;Quantidade;Custo 31/12/'.($report['year'] - 1).';Custo 31/12/'.$report['year'].';Discriminacao';

        foreach ($report['bens'] as $b) {
            $lines[] = implode(';', [
                $b['grupo_codigo'],
                $b['ticker'],
                str_replace(';', ',', $b['nome']),
                str_replace('.', ',', (string) $b['quantidade']),
                $n($b['custo_anterior']),
                $n($b['custo_atual']),
                str_replace(';', ',', $b['discriminacao']),
            ]);
        }

        $lines[] = '';
        $lines[] = "PROVENTOS {$report['year']}";
        $lines[] = 'Ticker;Nome;Isentos (dividendos/rendimentos);Tributacao exclusiva (JCP/juros);Outros';

        foreach ($report['proventos'] as $p) {
            $lines[] = implode(';', [
                $p['ticker'],
                str_replace(';', ',', $p['nome']),
                $n($p['isentos']),
                $n($p['exclusivos']),
                $n($p['outros']),
            ]);
        }

        $lines[] = '';
        $lines[] = "VENDAS POR MES {$report['year']}";
        $lines[] = 'Mes;Acoes;FIIs;Outros;Acoes acima da isencao de 20 mil';

        foreach ($report['vendas'] as $v) {
            $lines[] = implode(';', [
                $v['mes'],
                $n($v['acoes']),
                $n($v['fiis']),
                $n($v['outros']),
                $v['acoes_acima_isencao'] ? 'SIM' : 'nao',
            ]);
        }

        $lines[] = '';
        $lines[] = "GANHO DE CAPITAL {$report['year']}";
        $lines[] = 'Mes;Ganho acoes;Isento;DARF acoes;Ganho FIIs;DARF FIIs;DARF total';

        foreach ($report['ganhos'] as $g) {
            $lines[] = implode(';', [
                $g['mes'],
                $n($g['acoes']['ganho']),
                $g['acoes']['isento'] ? 'sim' : 'nao',
                $n($g['acoes']['darf']),
                $n($g['fiis']['ganho']),
                $n($g['fiis']['darf']),
                $n($g['darf']),
            ]);
        }

        return implode("\n", $lines);
    }
}
