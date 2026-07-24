<?php

namespace App\Filament\Resources\Lancamentos;

use App\Filament\Resources\Lancamentos\Pages\ListLancamentos;
use App\Models\Company;
use App\Models\Transaction;
use App\Support\CompanyFilter;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lançamentos avulsos: dinheiro que entra/sai sem estar ligado a um ativo
 * (assinaturas, impostos, serviços...), opcionalmente ligado a uma empresa.
 * Movimentações de ativos continuam no extrato de cada ativo.
 */
class LancamentoResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Carteira';

    protected static ?string $modelLabel = 'lançamento';

    protected static ?string $pluralModelLabel = 'lançamentos';

    protected static ?string $navigationLabel = 'Lançamentos';

    protected static ?string $slug = 'lancamentos';

    protected static ?int $navigationSort = 45;

    public const CATEGORIES = [
        'Assinaturas & Software' => 'Assinaturas & Software',
        'Serviços' => 'Serviços',
        'Impostos & Taxas' => 'Impostos & Taxas',
        'Folha / Pró-labore' => 'Folha / Pró-labore',
        'Aluguel' => 'Aluguel',
        'Manutenção' => 'Manutenção',
        'Financeiro' => 'Financeiro',
        'Outros' => 'Outros',
    ];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Filament::getTenant()->id)
            ->whereNull('asset_id')
            ->with('company');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Tipo')
                ->required()
                ->native(false)
                ->options([
                    'INCOME' => 'Receita',
                    'EXPENSE' => 'Despesa',
                ]),
            DatePicker::make('transaction_date')
                ->label('Data')
                ->required()
                ->default(now()),
            TextInput::make('movement')
                ->label('Descrição')
                ->placeholder('Ex: Assinatura Claude Code')
                ->required()
                ->maxLength(255),
            TextInput::make('total_amount')
                ->label('Valor')
                ->numeric()
                ->minValue(0)
                ->prefix('R$')
                ->required(),
            Select::make('category')
                ->label('Categoria')
                ->native(false)
                ->options(self::CATEGORIES)
                ->placeholder('Opcional'),
            Select::make('company_id')
                ->label('Empresa')
                ->relationship(
                    'company',
                    'name',
                    fn ($query) => $query->where('tenant_id', Filament::getTenant()->getKey()),
                )
                ->searchable()
                ->preload()
                ->placeholder('Opcional — de qual empresa é este lançamento'),
            Select::make('account_id')
                ->label('Conta')
                ->helperText('Vinculando uma conta, o dinheiro entra/sai do saldo dela (e do patrimônio).')
                ->relationship(
                    'account',
                    'name',
                    fn ($query) => $query->where('tenant_id', Filament::getTenant()->getKey()),
                )
                ->searchable()
                ->preload()
                ->placeholder('Opcional'),
            Textarea::make('notes')
                ->label('Observações')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('movement')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'EXPENSE' ? 'Despesa' : 'Receita')
                    ->color(fn (string $state): string => $state === 'EXPENSE' ? 'danger' : 'success'),
                TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Valor')
                    ->alignEnd()
                    ->color(fn (Transaction $record): string => $record->isCredit() ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state, Transaction $record): string => ($record->isCredit() ? '+' : '-')
                        .'R$ '.number_format((float) $state, 2, ',', '.'))
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'recurring' ? 'Recorrente' : 'Manual')
                    ->color(fn (string $state): string => $state === 'recurring' ? 'info' : 'gray'),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(['INCOME' => 'Receita', 'EXPENSE' => 'Despesa']),
                SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(self::CATEGORIES),
                SelectFilter::make('company')
                    ->label('Empresa')
                    ->options(fn (): array => [CompanyFilter::NONE => '— Sem empresa']
                        + Company::query()
                            ->where('tenant_id', Filament::getTenant()->id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                    ->query(fn (Builder $query, array $data): Builder => CompanyFilter::applyToCompanyColumn(
                        $query,
                        CompanyFilter::normalize($data['value'] ?? null),
                    )),
                Filter::make('last_12_months')
                    ->label('Últimos 12 meses')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('transaction_date', '>=', now()->subMonthsNoOverflow(12)->toDateString())),
            ])
            ->emptyStateHeading('Nenhum lançamento avulso')
            ->emptyStateDescription('Registre receitas e despesas que não pertencem a um ativo — assinaturas, impostos, serviços da empresa...');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLancamentos::route('/'),
        ];
    }
}
