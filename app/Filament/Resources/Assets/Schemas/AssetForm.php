<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Filament\Resources\Companies\CompanyForm;
use App\Models\Account;
use App\Models\Asset;
use App\Models\B3ListedTicker;
use App\Models\Company;
use App\Services\CurrencyConverter;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AssetForm
{
    /** Tipos cujo ticker vem do catálogo da B3. */
    private const LISTED_TYPES = ['STOCK', 'FII'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do ativo')
                    ->description('Identificação básica do investimento ou bem.')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Tipo')
                            ->required()
                            ->live()
                            ->native(false)
                            ->options([
                                'Investimentos' => [
                                    'STOCK' => Asset::TYPE_LABELS['STOCK'],
                                    'FII' => Asset::TYPE_LABELS['FII'],
                                    'FIXED_INCOME' => Asset::TYPE_LABELS['FIXED_INCOME'],
                                    'OPTION' => Asset::TYPE_LABELS['OPTION'],
                                ],
                                'Patrimônio físico' => [
                                    'VEHICLE' => Asset::TYPE_LABELS['VEHICLE'],
                                    'MACHINERY' => Asset::TYPE_LABELS['MACHINERY'],
                                    'REAL_ESTATE' => Asset::TYPE_LABELS['REAL_ESTATE'],
                                    'COMMODITY' => Asset::TYPE_LABELS['COMMODITY'],
                                    'COLLECTIBLE' => Asset::TYPE_LABELS['COLLECTIBLE'],
                                    'SOFTWARE' => Asset::TYPE_LABELS['SOFTWARE'],
                                    'OTHER' => Asset::TYPE_LABELS['OTHER'],
                                ],
                            ])
                            ->default('STOCK')
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $suggested = Asset::DEFAULT_DEPRECIATION_RATES[$state] ?? null;

                                if ($suggested !== null) {
                                    $set('metadata.depreciation_rate', $suggested);
                                }
                            }),
                        TextInput::make('name')
                            ->label('Nome do ativo')
                            ->placeholder(fn (Get $get): string => match (true) {
                                in_array($get('type'), Asset::PHYSICAL_TYPES, true) => 'Ex: Trator John Deere 6110J, Galpão Rod. BR-050',
                                $get('type') === 'FIXED_INCOME' => 'Ex: CDB Banco X 110% CDI',
                                default => 'Ex: PETROBRAS PN',
                            })
                            ->required()
                            ->maxLength(255),
                        Select::make('ticker_or_code')
                            ->label('Ticker (B3)')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => B3ListedTicker::query()
                                ->where(fn ($query) => $query
                                    ->where('ticker', 'like', mb_strtoupper($search).'%')
                                    ->orWhere('name', 'like', "%{$search}%"))
                                ->orderBy('ticker')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (B3ListedTicker $t): array => [$t->ticker => $t->label()])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): string => B3ListedTicker::query()
                                ->where('ticker', $value)->first()?->label() ?? (string) $value)
                            ->helperText('Escolha da lista de tickers negociáveis — é ele que liga o ativo às cotações.')
                            ->required(fn (Get $get): bool => in_array($get('type'), self::LISTED_TYPES, true))
                            ->visible(fn (Get $get): bool => in_array($get('type'), [...self::LISTED_TYPES, 'OPTION'], true)),
                        TextInput::make('ticker_or_code')
                            ->label('Código do título')
                            ->placeholder('Ex: CDB123ABC45')
                            ->maxLength(50)
                            ->visible(fn (Get $get): bool => $get('type') === 'FIXED_INCOME'),
                        Select::make('currency')
                            ->label('Moeda')
                            ->helperText('Lance os valores na moeda do ativo; a exibição converte para BRL pelo câmbio PTAX da data.')
                            ->required()
                            ->live()
                            ->native(false)
                            ->options([
                                'BRL' => 'Real (BRL)',
                                'USD' => 'Dólar (USD)',
                                'EUR' => 'Euro (EUR)',
                            ])
                            ->default('BRL'),
                        Select::make('company_id')
                            ->label('Empresa / Proprietária')
                            ->relationship(
                                'company',
                                'name',
                                fn ($query) => $query->where('tenant_id', Filament::getTenant()->getKey()),
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('Opcional — de quem é este ativo')
                            ->createOptionForm(CompanyForm::components())
                            ->createOptionUsing(function (array $data): int {
                                $data['tenant_id'] = Filament::getTenant()->getKey();

                                return Company::create($data)->getKey();
                            }),
                    ]),

                Section::make('Aquisição e depreciação')
                    ->description('O valor e a data de aquisição geram o primeiro lançamento do bem; a depreciação linear reduz o valor de mercado ao longo do tempo (reavaliações manuais zeram o relógio).')
                    ->columns(3)
                    ->visible(fn (Get $get): bool => in_array($get('type'), Asset::PHYSICAL_TYPES, true))
                    ->schema([
                        TextInput::make('acquisition_value')
                            ->label('Valor de aquisição')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (Get $get): string => CurrencyConverter::symbol($get('currency')))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false)
                            ->visibleOn('create'),
                        DatePicker::make('acquisition_date')
                            ->label('Data de aquisição')
                            ->default(now())
                            ->maxDate(now())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false)
                            ->visibleOn('create'),
                        TextInput::make('acquisition_quantity')
                            ->label('Quantidade')
                            ->helperText('Normalmente 1; use gramas para ouro, por exemplo.')
                            ->numeric()
                            ->minValue(0.000001)
                            ->default(1)
                            ->dehydrated(false)
                            ->visibleOn('create'),
                        Select::make('acquisition_account_id')
                            ->label('Debitar da conta (opcional)')
                            ->helperText('O valor da compra sai do saldo desta conta.')
                            ->options(fn (): array => Account::query()
                                ->where('tenant_id', Filament::getTenant()->getKey())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->dehydrated(false)
                            ->visibleOn('create'),
                        TextInput::make('metadata.depreciation_rate')
                            ->label('Depreciação anual')
                            ->helperText('Linear, sobre o valor base. Sugestões: veículos 20, máquinas 10, imóveis 4. Use 0 para não depreciar.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('% a.a.')
                            ->default(0),
                    ]),

                Section::make('Rentabilidade contratada')
                    ->description('Usada para corrigir o valor do título pelo indexador desde cada aporte.')
                    ->columns(3)
                    ->visible(fn (Get $get): bool => $get('type') === 'FIXED_INCOME')
                    ->schema([
                        Select::make('metadata.indexer')
                            ->label('Indexador')
                            ->live()
                            ->native(false)
                            ->options([
                                'CDI' => 'CDI',
                                'IPCA' => 'IPCA',
                                'SELIC' => 'SELIC',
                                'IGP-M' => 'IGP-M',
                                'PREFIXADO' => 'Prefixado',
                            ])
                            ->default('CDI'),
                        TextInput::make('metadata.index_percent')
                            ->label('% do índice')
                            ->helperText('Ex: 110 para "110% do CDI".')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('%')
                            ->default(100)
                            ->visible(fn (Get $get): bool => $get('metadata.indexer') !== 'PREFIXADO'),
                        TextInput::make('metadata.spread')
                            ->label(fn (Get $get): string => $get('metadata.indexer') === 'PREFIXADO' ? 'Taxa contratada' : 'Spread sobre o índice')
                            ->helperText('Ex: 5 para "IPCA + 5%", ou 12 para prefixado de 12% a.a.')
                            ->numeric()
                            ->suffix('% a.a.')
                            ->default(0),
                        TextInput::make('metadata.due_date')
                            ->label('Vencimento')
                            ->type('date')
                            ->helperText('Opcional — apenas informativo por enquanto.'),
                    ]),

                Section::make('Observações')
                    ->collapsed()
                    ->schema([
                        Textarea::make('metadata.notes')
                            ->label('Anotações')
                            ->rows(3)
                            ->placeholder('Notas livres sobre o ativo (tese, localização, número de série...).')
                            ->hiddenLabel(),
                    ]),
            ]);
    }
}
