<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Models\Asset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('transactions'))
            ->columns([
                TextColumn::make('name')
                    ->label('Ativo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ticker_or_code')
                    ->label('Ticker / Código')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'STOCK' => 'success',
                        'FII' => 'info',
                        'FIXED_INCOME' => 'warning',
                        'OPTION' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('transactions_count')
                    ->label('Movimentações')
                    ->counts('transactions')
                    ->badge()
                    ->alignCenter(),
                TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): float => $record->positionQuantity())
                    ->formatStateUsing(fn (float $state): string => rtrim(rtrim(number_format($state, 6, ',', '.'), '0'), ',')),
                TextColumn::make('purchase_value')
                    ->label('Valor de compra')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): float => $record->purchaseValue())
                    ->money('BRL'),
                TextColumn::make('current_value')
                    ->label('Valor atual')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): float => $record->currentValue())
                    ->money('BRL')
                    ->color(fn (Asset $record): string => $record->currentValue() >= $record->purchaseValue() ? 'success' : 'danger'),
                TextColumn::make('currency')
                    ->label('Moeda')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
