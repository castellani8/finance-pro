<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                KeyValue::make('metadata')
                    ->label('Metadados')
                    ->columnSpanFull(),
            ]);
    }
}
