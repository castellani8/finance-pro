<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Tenant;
use App\Services\B3MovementImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importB3Action(),
            CreateAction::make(),
        ];
    }

    protected function importB3Action(): Action
    {
        return Action::make('importB3')
            ->label('Importar planilha B3')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalHeading('Importar Movimentação da B3')
            ->modalDescription('Envie o arquivo .xlsx de "Movimentação" exportado da área do investidor da B3. Ativos e transações são atualizados sem duplicar.')
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
