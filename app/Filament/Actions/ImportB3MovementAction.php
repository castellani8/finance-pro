<?php

namespace App\Filament\Actions;

use App\Models\PortfolioSnapshot;
use App\Models\Tenant;
use App\Services\B3MovementImporter;
use App\Support\PortfolioCache;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;

/**
 * Ação de importar o relatório de Movimentação da B3, usada no cabeçalho da
 * listagem de ativos e no estado vazio (onboarding do primeiro acesso).
 */
class ImportB3MovementAction
{
    public static function make(): Action
    {
        return Action::make('importB3')
            ->label('Importar planilha B3')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalHeading('Importar Movimentação da B3')
            ->modalDescription('Envie o arquivo .xlsx de "Movimentação" exportado da área do investidor da B3 (Extratos > Movimentação > Filtrar > Exportar). Ativos e transações são atualizados sem duplicar.')
            ->modalSubmitActionLabel('Importar')
            ->schema([
                FileUpload::make('file')
                    ->label('Planilha (.xlsx)')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $file = $data['file'];

                if (is_array($file)) {
                    $file = reset($file);
                }

                /** @var Tenant $tenant */
                $tenant = Filament::getTenant();

                if (! $file || ! $tenant) {
                    Notification::make()
                        ->title('Não foi possível importar')
                        ->body('Arquivo ou tenant inválido.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $result = app(B3MovementImporter::class)->import($file->getRealPath(), $tenant);

                    // As fotografias diárias e os agregados em cache ficam
                    // obsoletos quando entram movimentações novas.
                    PortfolioSnapshot::where('tenant_id', $tenant->getKey())->delete();
                    PortfolioCache::bump($tenant->getKey());
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Falha na importação')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Importação concluída')
                    ->body(sprintf(
                        '%d ativos novos, %d atualizados. %d movimentações importadas, %d atualizadas, %d ignoradas.',
                        $result['assets_created'],
                        $result['assets_updated'],
                        $result['transactions_created'],
                        $result['transactions_updated'],
                        $result['skipped'],
                    ))
                    ->success()
                    ->send();
            });
    }
}
