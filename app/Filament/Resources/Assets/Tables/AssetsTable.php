<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Models\Asset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ativo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                TextColumn::make('ticker_or_code')
                    ->label('Ticker / Código')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'STOCK' => 'success',
                        'FII' => 'info',
                        'FIXED_INCOME' => 'warning',
                        'OPTION' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('rate_label')
                    ->label('Rentabilidade')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(fn (Asset $record): ?string => $record->rateLabel())
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'fixed_income'),
                TextColumn::make('transactions_count')
                    ->label('Movimentações')
                    ->counts('transactions')
                    ->badge()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->money('BRL'),
                TextColumn::make('dividends')
                    ->label('Proventos')
                    ->alignEnd()
                    ->color('success')
                    ->getStateUsing(fn (Asset $record): float => $record->dividendsReceived())
                    ->money('BRL'),
                TextColumn::make('variation')
                    ->label('Rent. s/ proventos')
                    ->alignEnd()
                    ->badge()
                    ->getStateUsing(fn (Asset $record): ?float => $record->percentChange())
                    ->formatStateUsing(fn (?float $state): string => self::formatPercent($state))
                    ->color(fn (?float $state): string => self::percentColor($state)),
                TextColumn::make('variation_with_dividends')
                    ->label('Rent. c/ proventos')
                    ->alignEnd()
                    ->badge()
                    ->getStateUsing(fn (Asset $record): ?float => $record->percentChangeWithDividends())
                    ->formatStateUsing(fn (?float $state): string => self::formatPercent($state))
                    ->color(fn (?float $state): string => self::percentColor($state)),
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

    private static function formatPercent(?float $state): string
    {
        if ($state === null) {
            return '—';
        }

        return sprintf('%s%s%%', $state >= 0 ? '+' : '', number_format($state, 2, ',', '.'));
    }

    private static function percentColor(?float $state): string
    {
        return match (true) {
            $state === null => 'gray',
            $state > 0 => 'success',
            $state < 0 => 'danger',
            default => 'gray',
        };
    }
}
