<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Widgets\AssetsPortfolioOverview;
use App\Models\Tenant;
use App\Services\B3MovementImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssets extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AssetResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AssetsPortfolioOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->importB3Action(),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'stock' => Tab::make('Ações')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'STOCK')),
            'fii' => Tab::make('FIIs')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'FII')),
            'fixed_income' => Tab::make('Renda Fixa')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'FIXED_INCOME')),
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
