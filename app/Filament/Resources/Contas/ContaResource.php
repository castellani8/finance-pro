<?php

namespace App\Filament\Resources\Contas;

use App\Filament\Resources\Contas\Pages\ListContas;
use App\Models\Account;
use App\Services\CurrencyConverter;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contas de dinheiro (banco, corretora, caixa). Lançamentos vinculados a uma
 * conta movem o saldo, e o saldo entra no patrimônio total.
 */
class ContaResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|\UnitEnum|null $navigationGroup = 'Carteira';

    protected static ?string $modelLabel = 'conta';

    protected static ?string $pluralModelLabel = 'contas';

    protected static ?string $navigationLabel = 'Contas';

    protected static ?string $slug = 'contas';

    protected static ?int $navigationSort = 44;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Filament::getTenant()->id)
            ->with(['company', 'transactions']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nome da conta')
                ->placeholder('Ex: Nubank PJ, Caixa da fazenda')
                ->required()
                ->maxLength(100),
            Select::make('kind')
                ->label('Tipo')
                ->required()
                ->native(false)
                ->options(Account::KIND_LABELS)
                ->default('bank'),
            Select::make('currency')
                ->label('Moeda')
                ->helperText('Contas em USD/EUR são convertidas para BRL pelo câmbio do dia no patrimônio.')
                ->required()
                ->live()
                ->native(false)
                ->options([
                    'BRL' => 'Real (BRL)',
                    'USD' => 'Dólar (USD)',
                    'EUR' => 'Euro (EUR)',
                ])
                ->default('BRL'),
            TextInput::make('opening_balance')
                ->label('Saldo inicial')
                ->helperText('O saldo de partida; os lançamentos vinculados movem a partir daqui.')
                ->numeric()
                ->prefix(fn (Get $get): string => CurrencyConverter::symbol($get('currency')))
                ->default(0)
                ->required(),
            Select::make('company_id')
                ->label('Empresa (opcional)')
                ->relationship(
                    'company',
                    'name',
                    fn ($query) => $query->where('tenant_id', Filament::getTenant()->getKey()),
                )
                ->searchable()
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Conta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => Account::KIND_LABELS[$state] ?? $state),
                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->placeholder('—'),
                TextColumn::make('opening_balance')
                    ->label('Saldo inicial')
                    ->alignEnd()
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transactions_count')
                    ->label('Lançamentos')
                    ->counts('transactions')
                    ->badge()
                    ->alignCenter(),
                TextColumn::make('balance')
                    ->label('Saldo atual')
                    ->alignEnd()
                    ->getStateUsing(fn (Account $record): float => $record->balance())
                    ->formatStateUsing(fn (float $state, Account $record): string => ($state < 0 ? '-' : '')
                        .CurrencyConverter::symbol($record->currency).' '.number_format(abs($state), 2, ',', '.'))
                    ->tooltip(fn (Account $record): ?string => $record->currency !== 'BRL'
                        ? '≈ R$ '.number_format($record->balanceInBrlAt(), 2, ',', '.').' pelo câmbio de hoje'
                        : null)
                    ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success')
                    ->weight('bold'),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Nenhuma conta cadastrada')
            ->emptyStateDescription('Cadastre suas contas (banco, corretora, caixa) e vincule lançamentos a elas — o saldo entra no seu patrimônio total.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContas::route('/'),
        ];
    }
}
