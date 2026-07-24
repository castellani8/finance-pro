<?php

namespace App\Filament\Resources\Recorrencias;

use App\Filament\Resources\Lancamentos\LancamentoResource;
use App\Filament\Resources\Recorrencias\Pages\ListRecorrencias;
use App\Models\Company;
use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionGenerator;
use App\Support\CompanyFilter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contratos recorrentes: aluguel mensal do trator, assinatura de software...
 * O scheduler (ledger:generate-recurring) materializa cada vencimento em um
 * lançamento real; o botão "Gerar pendentes" faz o mesmo na hora.
 */
class RecorrenciaResource extends Resource
{
    protected static ?string $model = RecurringTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Carteira';

    protected static ?string $modelLabel = 'recorrência';

    protected static ?string $pluralModelLabel = 'recorrências';

    protected static ?string $navigationLabel = 'Recorrências';

    protected static ?string $slug = 'recorrencias';

    protected static ?int $navigationSort = 46;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Filament::getTenant()->id)
            ->with(['asset', 'company']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('description')
                ->label('Descrição')
                ->placeholder('Ex: Aluguel do trator — Cliente Y, Assinatura Claude Code')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label('Tipo')
                ->required()
                ->native(false)
                ->options([
                    'INCOME' => 'Receita',
                    'EXPENSE' => 'Despesa',
                ]),
            TextInput::make('amount')
                ->label('Valor mensal')
                ->numeric()
                ->minValue(0.01)
                ->prefix('R$')
                ->required(),
            TextInput::make('day_of_month')
                ->label('Dia do vencimento')
                ->helperText('1 a 31 — em meses curtos cai no último dia.')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->default(5)
                ->required(),
            DatePicker::make('starts_on')
                ->label('Início')
                ->required()
                ->default(now()->startOfMonth()),
            DatePicker::make('ends_on')
                ->label('Fim (opcional)')
                ->helperText('Deixe vazio para contrato sem prazo.')
                ->afterOrEqual('starts_on'),
            Select::make('asset_id')
                ->label('Ativo (opcional)')
                ->helperText('Ex: o trator que gera este aluguel — a renda entra no resultado do ativo.')
                ->relationship(
                    'asset',
                    'name',
                    fn ($query) => $query->where('tenant_id', Filament::getTenant()->getKey()),
                )
                ->searchable()
                ->preload(),
            Select::make('company_id')
                ->label('Empresa (opcional)')
                ->relationship(
                    'company',
                    'name',
                    fn ($query) => $query->where('tenant_id', Filament::getTenant()->getKey()),
                )
                ->searchable()
                ->preload(),
            Select::make('account_id')
                ->label('Conta (opcional)')
                ->helperText('Cada lançamento gerado movimenta o saldo desta conta.')
                ->relationship(
                    'account',
                    'name',
                    fn ($query) => $query->where('tenant_id', Filament::getTenant()->getKey()),
                )
                ->searchable()
                ->preload(),
            Select::make('category')
                ->label('Categoria')
                ->native(false)
                ->options(LancamentoResource::CATEGORIES)
                ->placeholder('Opcional'),
            Toggle::make('active')
                ->label('Ativa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(35),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'EXPENSE' ? 'Despesa' : 'Receita')
                    ->color(fn (string $state): string => $state === 'EXPENSE' ? 'danger' : 'success'),
                TextColumn::make('amount')
                    ->label('Valor mensal')
                    ->alignEnd()
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('day_of_month')
                    ->label('Dia')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => "dia {$state}"),
                TextColumn::make('asset.name')
                    ->label('Ativo')
                    ->limit(25)
                    ->placeholder('—'),
                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->limit(25)
                    ->placeholder('—'),
                TextColumn::make('last_generated_on')
                    ->label('Último lançamento')
                    ->date('d/m/Y')
                    ->placeholder('nunca'),
                IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->defaultSort('description')
            ->filters([
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
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(['INCOME' => 'Receita', 'EXPENSE' => 'Despesa']),
            ])
            ->emptyStateHeading('Nenhuma recorrência cadastrada')
            ->emptyStateDescription('Cadastre contratos que se repetem todo mês — aluguel de um bem, assinatura de software — e os lançamentos serão gerados automaticamente.');
    }

    /** Ação compartilhada de gerar os vencimentos pendentes agora. */
    public static function generateNowAction(): Action
    {
        return Action::make('generateNow')
            ->label('Gerar pendentes agora')
            ->icon('heroicon-o-bolt')
            ->color('gray')
            ->action(function (): void {
                $created = app(RecurringTransactionGenerator::class)->generateDue(Filament::getTenant());

                Notification::make()
                    ->title($created > 0 ? "{$created} lançamento(s) gerado(s)" : 'Nada pendente para gerar')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecorrencias::route('/'),
        ];
    }
}
