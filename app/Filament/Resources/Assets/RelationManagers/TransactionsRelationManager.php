<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Models\Asset;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Support\PortfolioCache;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Extrato de movimentações do ativo. Lançamentos manuais (source=manual)
 * podem ser criados, editados e excluídos; as linhas importadas da B3 são
 * somente leitura e atualizadas por reimportação.
 */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Movimentações';

    /** Tipos em que quantidade não faz sentido (o valor total é o que importa). */
    private const NO_QUANTITY_TYPES = ['IMPROVEMENT', 'EXPENSE', 'REVALUATION', 'INCOME', 'DIVIDEND', 'JCP', 'INTEREST', 'AMORTIZATION'];

    /** Tipos lançados como saída (Débito). */
    private const DEBIT_TYPES = ['SELL', 'EXPENSE'];

    public const TYPE_LABELS = [
        'BUY' => 'Compra',
        'SELL' => 'Venda',
        'DIVIDEND' => 'Dividendo',
        'JCP' => 'JCP',
        'INCOME' => 'Rendimento',
        'INTEREST' => 'Juros',
        'AMORTIZATION' => 'Amortização',
        'BONUS' => 'Bonificação',
        'SPLIT' => 'Desdobramento',
        'GROUPING' => 'Grupamento',
        'SUBSCRIPTION' => 'Subscrição',
        'RIGHTS_CESSION' => 'Cessão de direitos',
        'FRACTION_AUCTION' => 'Leilão de fração',
        'TRANSFER' => 'Transferência',
        'UPDATE' => 'Atualização',
        'CUSTODY_BLOCK' => 'Bloqueio de custódia',
        'IMPROVEMENT' => 'Benfeitoria / Investimento',
        'EXPENSE' => 'Despesa / Manutenção',
        'REVALUATION' => 'Reavaliação',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Tipo de lançamento')
                ->required()
                ->live()
                ->native(false)
                ->options($this->manualTypeOptions()),
            DatePicker::make('transaction_date')
                ->label('Data')
                ->required()
                ->default(now()),
            TextInput::make('quantity')
                ->label('Quantidade')
                ->numeric()
                ->minValue(0.000001)
                ->default(1)
                ->visible(fn (Get $get): bool => ! in_array($get('type'), self::NO_QUANTITY_TYPES, true)),
            TextInput::make('total_amount')
                ->label(fn (Get $get): string => $get('type') === 'REVALUATION' ? 'Novo valor de mercado do bem' : 'Valor total')
                ->helperText(fn (Get $get): ?string => match ($get('type')) {
                    'REVALUATION' => 'Redefine o valor do ativo nesta data; a depreciação volta a contar daqui.',
                    'IMPROVEMENT' => 'Ex: reforma, peças, horas trabalhadas valorizadas em R$.',
                    'EXPENSE' => 'Soma ao custo investido (não altera o valor de mercado).',
                    default => null,
                })
                ->numeric()
                ->minValue(0)
                ->prefix('R$')
                ->required(),
            Textarea::make('notes')
                ->label('Observações')
                ->rows(2)
                ->placeholder('Opcional — ex: troca do motor, NF 1234...')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('movement')
                    ->label('Movimentação')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::TYPE_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'BUY', 'BONUS', 'SPLIT', 'IMPROVEMENT' => 'success',
                        'SELL', 'EXPENSE' => 'danger',
                        'DIVIDEND', 'JCP', 'INCOME', 'INTEREST', 'AMORTIZATION', 'FRACTION_AUCTION' => 'info',
                        'REVALUATION' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('direction')
                    ->label('Sentido')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'Credito' ? 'success' : 'danger')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 6, ',', '.'), '0'), ',')),
                TextColumn::make('unit_price')
                    ->label('Preço unitário')
                    ->alignEnd()
                    ->money('BRL')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')
                    ->label('Valor total')
                    ->alignEnd()
                    ->money('BRL'),
                TextColumn::make('institution')
                    ->label('Instituição')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manual' => 'Manual',
                        'recurring' => 'Recorrente',
                        'b3' => 'B3',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'info',
                        'recurring' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('notes')
                    ->label('Observações')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(self::TYPE_LABELS),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Lançar transação')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Lançar transação manual')
                    ->mutateDataUsing(fn (array $data): array => $this->prepareManualData($data))
                    ->after(fn () => $this->invalidateAggregates()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Transaction $record): bool => in_array($record->source, ['manual', 'recurring'], true))
                    ->mutateDataUsing(fn (array $data): array => $this->prepareManualData($data))
                    ->after(fn () => $this->invalidateAggregates()),
                DeleteAction::make()
                    ->visible(fn (Transaction $record): bool => in_array($record->source, ['manual', 'recurring'], true))
                    ->after(fn () => $this->invalidateAggregates()),
            ])
            ->defaultSort('transaction_date', 'desc');
    }

    /** Opções de lançamento manual conforme a classe do ativo. */
    private function manualTypeOptions(): array
    {
        /** @var Asset $asset */
        $asset = $this->getOwnerRecord();

        if ($asset->isPhysical()) {
            return [
                'BUY' => 'Aquisição',
                'IMPROVEMENT' => 'Benfeitoria / Investimento',
                'EXPENSE' => 'Despesa / Manutenção',
                'REVALUATION' => 'Reavaliação (novo valor de mercado)',
                'INCOME' => 'Renda (aluguel, licença...)',
                'SELL' => 'Venda',
            ];
        }

        return [
            'BUY' => 'Compra',
            'SELL' => 'Venda',
            'DIVIDEND' => 'Dividendo',
            'JCP' => 'JCP',
            'INCOME' => 'Rendimento',
            'INTEREST' => 'Juros',
            'AMORTIZATION' => 'Amortização',
            'BONUS' => 'Bonificação',
        ];
    }

    /** Completa os campos derivados de um lançamento manual. */
    private function prepareManualData(array $data): array
    {
        $quantity = (float) ($data['quantity'] ?? 1);
        $total = (float) ($data['total_amount'] ?? 0);

        $data['tenant_id'] = Filament::getTenant()->getKey();
        $data['source'] = 'manual';
        $data['direction'] = in_array($data['type'] ?? '', self::DEBIT_TYPES, true) ? 'Debito' : 'Credito';
        $data['quantity'] = $quantity > 0 ? $quantity : 1;
        $data['unit_price'] = $quantity > 0 ? $total / $quantity : null;

        return $data;
    }

    private function invalidateAggregates(): void
    {
        $tenantId = Filament::getTenant()->getKey();

        PortfolioSnapshot::where('tenant_id', $tenantId)->delete();
        PortfolioCache::bump($tenantId);
    }
}
