<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome do ativo')
                    ->required(),
                Select::make('type')
                    ->label('Tipo')
                    ->required()
                    ->live()
                    ->options([
                        'STOCK' => 'Ação',
                        'FII' => 'Fundo Imobiliário',
                        'FIXED_INCOME' => 'Renda Fixa',
                        'OPTION' => 'Opção',
                        'OTHER' => 'Outro',
                    ])
                    ->default('STOCK'),
                TextInput::make('ticker_or_code')
                    ->label('Ticker / Código'),
                TextInput::make('currency')
                    ->label('Moeda')
                    ->required()
                    ->default('BRL'),
                Select::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'name'),
                Select::make('metadata.indexer')
                    ->label('Indexador')
                    ->live()
                    ->options([
                        'CDI' => 'CDI',
                        'IPCA' => 'IPCA',
                        'SELIC' => 'SELIC',
                        'PREFIXADO' => 'Prefixado',
                    ])
                    ->default('CDI')
                    ->visible(fn (Get $get): bool => $get('type') === 'FIXED_INCOME'),
                TextInput::make('metadata.index_percent')
                    ->label('% do índice')
                    ->helperText('Ex: 100 para 100% do CDI, 110 para 110% do CDI.')
                    ->numeric()
                    ->suffix('%')
                    ->default(100)
                    ->visible(fn (Get $get): bool => $get('type') === 'FIXED_INCOME' && $get('metadata.indexer') !== 'PREFIXADO'),
                TextInput::make('metadata.spread')
                    ->label(fn (Get $get): string => $get('metadata.indexer') === 'PREFIXADO' ? 'Taxa (a.a.)' : 'Spread (+ a.a.)')
                    ->helperText('Ex: 4 para "CDI + 4%" / "IPCA + 4%", ou a taxa do prefixado.')
                    ->numeric()
                    ->suffix('% a.a.')
                    ->default(0)
                    ->visible(fn (Get $get): bool => $get('type') === 'FIXED_INCOME'),
            ]);
    }
}
