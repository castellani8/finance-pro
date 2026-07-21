<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Actions\ImportB3MovementAction;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Widgets\AssetsPortfolioOverview;
use App\Models\Asset;
use App\Services\AssetMetricsRefresher;
use App\Support\PortfolioCache;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListAssets extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AssetResource::class;

    /** Uma query só para as cotações de todos os tickers da página. */
    public function getTableRecords(): Paginator|Collection
    {
        $records = parent::getTableRecords();

        Asset::primeMarketData($records);

        return $records;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AssetsPortfolioOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshMetrics')
                ->label('Atualizar valores')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip('Recalcula posição, investido e valor atual de todos os ativos (preços e câmbio mais recentes).')
                ->action(function (): void {
                    $tenant = Filament::getTenant();
                    $count = app(AssetMetricsRefresher::class)->refreshTenant($tenant);
                    PortfolioCache::bump($tenant->getKey());

                    Notification::make()
                        ->title("{$count} ativo(s) recalculado(s)")
                        ->success()
                        ->send();
                }),
            ImportB3MovementAction::make(),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),
            'stock' => Tab::make('Ações')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'STOCK')),
            'fii' => Tab::make('FIIs')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'FII')),
            'fixed_income' => Tab::make('Renda Fixa')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'FIXED_INCOME')),
            'physical' => Tab::make('Patrimônio')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', Asset::PHYSICAL_TYPES)),
        ];
    }
}
