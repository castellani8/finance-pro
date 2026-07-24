<?php

namespace App\Filament\Resources\Assets;

use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Filament\Resources\Assets\Tables\AssetsTable;
use App\Models\Asset;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Carteira';

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // A ordenação padrão fica na tabela (defaultSort): um orderBy fixo aqui
        // viria antes de qualquer ordenação escolhida pelo usuário e a anularia.
        return parent::getEloquentQuery()
            ->where('tenant_id', Filament::getTenant()->id)
            // Posições vendidas de opções (quantidade negativa) também aparecem.
            ->whereOpenPosition()
            ->with('transactions');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }
}
