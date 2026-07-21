<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Actions\ImportB3MovementAction;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Widgets\AssetsPortfolioOverview;
use App\Models\Asset;
use Filament\Actions\CreateAction;
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
