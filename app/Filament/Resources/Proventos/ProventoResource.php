<?php

namespace App\Filament\Resources\Proventos;

use App\Filament\Resources\Proventos\Pages\ListProventos;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Transaction;
use App\Support\CompanyFilter;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Extrato de proventos (dividendos, JCP, rendimentos, juros, amortizações e
 * leilões de fração), agrupado por mês — somente leitura, alimentado pela
 * importação da B3.
 */
class ProventoResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'provento';

    protected static ?string $pluralModelLabel = 'proventos';

    protected static ?string $navigationLabel = 'Proventos';

    protected static ?string $slug = 'proventos';

    public static function getEloquentQuery(): Builder
    {
        // Proventos = renda DE ATIVOS (dividendos, aluguéis...); receitas
        // avulsas (sem ativo) vivem na tela de Lançamentos.
        return parent::getEloquentQuery()
            ->where('tenant_id', Filament::getTenant()->id)
            ->whereIn('type', Asset::CASH_INCOME_TYPES)
            ->whereNotNull('asset_id')
            ->with('asset');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('asset.ticker_or_code')
                    ->label('Ativo')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('asset.name')
                    ->label('Nome')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'DIVIDEND' => 'Dividendo',
                        'JCP' => 'JCP',
                        'INCOME' => 'Rendimento',
                        'INTEREST' => 'Juros',
                        'AMORTIZATION' => 'Amortização',
                        'FRACTION_AUCTION' => 'Leilão de fração',
                        default => $state,
                    })
                    ->color('info')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Qtd. base')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 6, ',', '.'), '0'), ','))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')
                    ->label('Valor')
                    ->alignEnd()
                    ->money('BRL')
                    ->color(fn (Transaction $record): string => $record->isCredit() ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state, Transaction $record): string => ($record->isCredit() ? '' : '-')
                        .'R$ '.number_format((float) $state, 2, ',', '.'))
                    ->sortable()
                    // Soma com sinal: estornos em débito subtraem do total.
                    ->summarize(Summarizer::make()
                        ->label('Total')
                        ->using(fn (QueryBuilder $query): float => (float) $query->sum(
                            DB::raw("case when direction = 'Credito' then total_amount else -total_amount end")
                        ))
                        ->money('BRL')),
                TextColumn::make('institution')
                    ->label('Instituição')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->defaultGroup(
                Group::make('transaction_date')
                    ->label('Mês')
                    ->getTitleFromRecordUsing(fn (Transaction $record): string => $record->transaction_date
                        ->locale('pt_BR')
                        ->translatedFormat('F \d\e Y'))
                    ->getKeyFromRecordUsing(fn (Transaction $record): string => $record->transaction_date->format('Y-m'))
                    // A chave do grupo é "Y-m"; sem isto o Filament compararia
                    // transaction_date = '2026-07', que o PostgreSQL rejeita.
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $query->whereBetween(
                        'transaction_date',
                        [
                            $key.'-01',
                            \Carbon\CarbonImmutable::createFromFormat('!Y-m', $key)->endOfMonth()->toDateString(),
                        ],
                    ))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('transaction_date', 'desc'))
            )
            ->groupingSettingsHidden()
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'DIVIDEND' => 'Dividendo',
                        'JCP' => 'JCP',
                        'INCOME' => 'Rendimento',
                        'INTEREST' => 'Juros',
                        'AMORTIZATION' => 'Amortização',
                        'FRACTION_AUCTION' => 'Leilão de fração',
                    ]),
                SelectFilter::make('asset_id')
                    ->label('Ativo')
                    ->relationship('asset', 'ticker_or_code', fn (Builder $query): Builder => $query
                        ->where('tenant_id', Filament::getTenant()->id)
                        ->whereNotNull('ticker_or_code'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('company')
                    ->label('Empresa')
                    ->options(fn (): array => [CompanyFilter::NONE => '— Sem empresa (pessoal)']
                        + Company::query()
                            ->where('tenant_id', Filament::getTenant()->id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $companyId = CompanyFilter::normalize($data['value'] ?? null);

                        return $query->when($companyId !== null, fn (Builder $q): Builder => $q
                            ->whereIn('asset_id', fn ($sub) => CompanyFilter::applyToCompanyColumn(
                                $sub->select('id')->from('assets'),
                                $companyId,
                            )));
                    }),
                Filter::make('last_12_months')
                    ->label('Últimos 12 meses')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('transaction_date', '>=', now()->subMonthsNoOverflow(12)->toDateString())),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProventos::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
