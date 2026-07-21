<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Filament\Actions\ImportB3MovementAction;
use App\Models\Asset;
use App\Models\Company;
use App\Support\CompanyFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ativo')
                    ->searchable()
                    ->limit(35)
                    ->sortable(),

                TextColumn::make('ticker_or_code')
                    ->label('Ticker / Código')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    // Carro, casa e trator não têm ticker.
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) !== 'physical'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->toggleable()
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Asset::TYPE_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'STOCK' => 'success',
                        'FII' => 'info',
                        'FIXED_INCOME' => 'warning',
                        'OPTION' => 'danger',
                        'VEHICLE', 'MACHINERY', 'REAL_ESTATE', 'COMMODITY', 'COLLECTIBLE', 'SOFTWARE' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('rate_label')
                    ->label('Rentabilidade')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(fn (Asset $record): ?string => $record->rateLabel())
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'fixed_income'),
                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->badge()
                    ->getStateUsing(fn (Asset $record): ?string => $record->metadata['due_date'] ?? null)
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? Carbon::parse($state)->format('d/m/Y')
                        : '—')
                    ->color(function (?string $state): string {
                        if (! $state) {
                            return 'gray';
                        }

                        $days = now()->startOfDay()->diffInDays(Carbon::parse($state), false);

                        return match (true) {
                            $days < 0 => 'danger',
                            $days <= 30 => 'warning',
                            default => 'gray',
                        };
                    })
                    ->placeholder('—')
                    // Vencimento importa tanto para renda fixa quanto para opções.
                    ->visible(fn ($livewire): bool => in_array($livewire->activeTab ?? null, ['fixed_income', 'option'], true)),
                TextColumn::make('depreciation_rate')
                    ->label('Depreciação')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(fn (Asset $record): ?float => $record->depreciationRate() > 0 ? $record->depreciationRate() : null)
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : rtrim(rtrim(number_format($state, 2, ',', '.'), '0'), ',').'% a.a.')
                    ->placeholder('—')
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'physical'),
                TextColumn::make('income_12m')
                    ->label('Renda 12m')
                    ->alignEnd()
                    ->color('success')
                    ->getStateUsing(fn (Asset $record): float => $record->incomeLastTwelveMonths())
                    ->money('BRL')
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'physical')
                    ->sortable(query: self::sortByComputed('incomeLastTwelveMonths')),
                TextColumn::make('expenses_12m')
                    ->label('Despesas 12m')
                    ->alignEnd()
                    ->color('danger')
                    ->getStateUsing(fn (Asset $record): float => $record->expensesLastTwelveMonths())
                    ->money('BRL')
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'physical')
                    ->sortable(query: self::sortByComputed('expensesLastTwelveMonths')),
                TextColumn::make('net_result')
                    ->label('Resultado')
                    ->alignEnd()
                    ->badge()
                    ->tooltip('Tudo que o bem rendeu menos tudo que custou de despesas (total).')
                    ->getStateUsing(fn (Asset $record): float => $record->netResult())
                    ->formatStateUsing(fn (float $state): string => ($state >= 0 ? '+' : '-').'R$ '.number_format(abs($state), 2, ',', '.'))
                    ->color(fn (float $state): string => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'physical')
                    ->sortable(query: self::sortByComputed('netResult')),
                TextColumn::make('cap_rate')
                    ->label('Yield 12m')
                    ->alignEnd()
                    ->badge()
                    ->color('info')
                    ->tooltip('Renda dos últimos 12 meses sobre o valor atual do bem.')
                    ->getStateUsing(fn (Asset $record): ?float => $record->dividendYield())
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 2, ',', '.').'%')
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'physical')
                    ->sortable(query: self::sortByComputed('dividendYield')),
                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->limit(25)
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable()
                    ->visible(fn ($livewire): bool => in_array($livewire->activeTab ?? null, ['physical', 'all', null], true)),
                TextColumn::make('institution')
                    ->label('Instituição')
                    ->getStateUsing(fn (Asset $record): ?string => $record->currentInstitution())
                    ->limit(30)
                    ->placeholder('—')
                    // Ativo físico não tem corretora custodiante.
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) !== 'physical')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        '(select transactions.institution from transactions
                          where transactions.asset_id = assets.id and transactions.institution is not null
                          order by transactions.transaction_date desc, transactions.id desc limit 1) '.$direction
                    )),
                TextColumn::make('transactions_count')
                    ->label('Movimentações')
                    ->counts('transactions')
                    ->badge()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): float => $record->positionQuantity())
                    ->formatStateUsing(fn (float $state): string => rtrim(rtrim(number_format($state, 6, ',', '.'), '0'), ',')
                        .($state < 0 ? ' (lançada)' : ''))
                    ->color(fn (float $state): ?string => $state < 0 ? 'danger' : null)
                    // Bens físicos são normalmente unitários; a quantidade fica no extrato.
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) !== 'physical')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('position_quantity', $direction)),
                TextColumn::make('average_buy_price')
                    ->label('Preço médio')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): float => $record->averageBuyPrice())
                    ->money('BRL')
                    ->visible(fn ($livewire): bool => self::isVariableIncomeTab($livewire))
                    ->sortable(query: self::sortByComputed('averageBuyPrice')),
                TextColumn::make('current_unit_price')
                    ->label('Valor atual unit.')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): ?float => $record->currentUnitPrice())
                    ->money('BRL')
                    ->placeholder('—')
                    ->visible(fn ($livewire): bool => self::isVariableIncomeTab($livewire))
                    ->sortable(query: self::sortByComputed('currentUnitPrice')),
                TextColumn::make('daily_change')
                    ->label('Var. dia')
                    ->alignEnd()
                    ->badge()
                    ->getStateUsing(fn (Asset $record): ?float => $record->dailyChangePercent())
                    ->formatStateUsing(fn (?float $state): string => self::formatPercent($state))
                    ->color(fn (?float $state): string => self::percentColor($state))
                    ->visible(fn ($livewire): bool => self::isVariableIncomeTab($livewire))
                    ->sortable(query: self::sortByComputed('dailyChangePercent')),
                TextColumn::make('dividend_yield')
                    ->label('DY 12m')
                    ->alignEnd()
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Asset $record): ?float => $record->dividendYield())
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 2, ',', '.').'%')
                    ->visible(fn ($livewire): bool => self::isVariableIncomeTab($livewire))
                    ->sortable(query: self::sortByComputed('dividendYield')),
                TextColumn::make('yield_on_cost')
                    ->label('Yield on Cost 12m')
                    ->alignEnd()
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn (Asset $record): ?float => $record->yieldOnCost())
                    ->formatStateUsing(fn (?float $state): string => $state === null ? '—' : number_format($state, 2, ',', '.').'%')
                    ->visible(fn ($livewire): bool => self::isVariableIncomeTab($livewire))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(query: self::sortByComputed('yieldOnCost')),
                TextColumn::make('purchase_value')
                    ->label('Valor de compra')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): float => $record->acquisitionValue())
                    ->money('BRL')
                    ->sortable(query: self::sortByComputed('acquisitionValue')),
                TextColumn::make('invested_total')
                    ->label('Custo total')
                    ->tooltip('Compra + benfeitorias + despesas — tudo que já entrou neste bem.')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): float => $record->purchaseValue())
                    ->money('BRL')
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) === 'physical')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('invested_value', $direction)),
                TextColumn::make('current_value')
                    ->label('Valor atual')
                    ->alignEnd()
                    ->getStateUsing(fn (Asset $record): float => $record->currentValue())
                    ->money('BRL')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('current_value', $direction)),
                TextColumn::make('dividends')
                    ->label('Proventos')
                    ->alignEnd()
                    ->color('success')
                    ->getStateUsing(fn (Asset $record): float => $record->dividendsReceived())
                    ->money('BRL')
                    ->sortable(query: self::sortByComputed('dividendsReceived')),
                TextColumn::make('variation')
                    ->label('Rent. s/ proventos')
                    ->alignEnd()
                    ->badge()
                    ->getStateUsing(fn (Asset $record): ?float => $record->percentChange())
                    ->formatStateUsing(fn (?float $state): string => self::formatPercent($state))
                    ->color(fn (?float $state): string => self::percentColor($state))
                    ->sortable(query: self::sortByComputed('percentChange')),
                TextColumn::make('variation_with_dividends')
                    ->label('Rent. c/ proventos')
                    ->alignEnd()
                    ->badge()
                    ->getStateUsing(fn (Asset $record): ?float => $record->percentChangeWithDividends())
                    ->formatStateUsing(fn (?float $state): string => self::formatPercent($state))
                    ->color(fn (?float $state): string => self::percentColor($state))
                    ->sortable(query: self::sortByComputed('percentChangeWithDividends')),
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
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('company')
                    ->label('Empresa')
                    ->options(fn (): array => [CompanyFilter::NONE => '— Sem empresa (pessoal)']
                        + Company::query()
                            ->where('tenant_id', Filament::getTenant()->id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                    ->query(fn (Builder $query, array $data): Builder => CompanyFilter::applyToCompanyColumn(
                        $query,
                        CompanyFilter::normalize($data['value'] ?? null),
                    )),
            ])
            ->emptyStateHeading('Sua carteira ainda está vazia')
            ->emptyStateDescription('Importe o relatório de "Movimentação" da B3 e veja todo o seu histórico consolidado em segundos — posições, proventos e rentabilidade.')
            ->emptyStateIcon('heroicon-o-arrow-up-tray')
            ->emptyStateActions([
                ImportB3MovementAction::make(),
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

    /** Colunas de renda variável só aparecem nas tabs de Ações, FIIs e Opções. */
    private static function isVariableIncomeTab($livewire): bool
    {
        return in_array($livewire->activeTab ?? null, ['stock', 'fii', 'option'], true);
    }

    /**
     * Ordena por uma métrica calculada em PHP (posição, valores, rentabilidade):
     * como grupamento e preço médio não são expressáveis em SQL puro, calcula a
     * métrica para os ativos filtrados e devolve a ordem via CASE por id.
     */
    private static function sortByComputed(string $method): \Closure
    {
        return function (Builder $query, string $direction) use ($method): Builder {
            $ids = (clone $query)
                ->reorder()
                ->with('transactions')
                ->get()
                ->sortBy(fn (Asset $asset) => $asset->{$method}() ?? -INF, SORT_REGULAR, $direction === 'desc')
                ->values()
                ->modelKeys();

            if ($ids === []) {
                return $query;
            }

            $cases = implode(' ', array_map(
                fn (int $position, $id): string => "when {$id} then {$position}",
                array_keys($ids),
                $ids,
            ));

            return $query->orderByRaw("case assets.id {$cases} else ".count($ids).' end');
        };
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
