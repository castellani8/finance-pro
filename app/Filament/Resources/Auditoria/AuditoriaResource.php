<?php

namespace App\Filament\Resources\Auditoria;

use App\Filament\Resources\Auditoria\Pages\ListAuditoria;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Company;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Trilha de auditoria das ações manuais: quem criou/alterou/excluiu o quê.
 * Importações da B3 e lançamentos gerados por recorrência ficam de fora
 * (são eventos de sistema, com origem própria).
 */
class AuditoriaResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Minha conta';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $pluralModelLabel = 'auditoria';

    protected static ?string $navigationLabel = 'Auditoria';

    protected static ?string $slug = 'auditoria';

    protected static ?int $navigationSort = 89;

    /**
     * O model Activity (Spatie) não tem relação tenant — o escopo por tenant
     * é feito manualmente via whereHasMorph no getEloquentQuery.
     */
    protected static bool $isScopedToTenant = false;

    private const SUBJECT_LABELS = [
        Transaction::class => 'Lançamento',
        Asset::class => 'Ativo',
        Company::class => 'Empresa',
        Account::class => 'Conta',
        RecurringTransaction::class => 'Recorrência',
    ];

    private const EVENT_LABELS = [
        'created' => 'Criou',
        'updated' => 'Alterou',
        'deleted' => 'Excluiu',
    ];

    public static function getEloquentQuery(): Builder
    {
        // Escopo por tenant via o assunto do registro (todos têm tenant_id).
        return parent::getEloquentQuery()
            ->whereHasMorph(
                'subject',
                array_keys(self::SUBJECT_LABELS),
                fn (Builder $query) => $query->where('tenant_id', Filament::getTenant()->id),
            )
            ->with(['causer', 'subject']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Quem')
                    ->placeholder('Sistema'),
                TextColumn::make('event')
                    ->label('Ação')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::EVENT_LABELS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('O quê')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => self::SUBJECT_LABELS[$state] ?? class_basename((string) $state)),
                TextColumn::make('subject_summary')
                    ->label('Detalhe')
                    ->getStateUsing(function (Activity $record): string {
                        $subject = $record->subject;

                        return match (true) {
                            $subject instanceof Transaction => trim(($subject->movement ?? $subject->type).' — R$ '.number_format((float) $subject->total_amount, 2, ',', '.')),
                            $subject !== null && isset($subject->name) => (string) $subject->name,
                            $subject !== null && isset($subject->description) => (string) $subject->description,
                            default => '(registro excluído)',
                        };
                    })
                    ->limit(45),
                TextColumn::make('changes_summary')
                    ->label('Campos alterados')
                    ->getStateUsing(function (Activity $record): string {
                        $changed = array_keys($record->properties['attributes'] ?? []);

                        return $changed === [] ? '—' : implode(', ', array_slice($changed, 0, 6));
                    })
                    ->limit(60)
                    ->tooltip(fn (Activity $record): ?string => json_encode($record->properties, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Ação')
                    ->options(self::EVENT_LABELS),
                SelectFilter::make('subject_type')
                    ->label('Tipo')
                    ->options(array_combine(array_keys(self::SUBJECT_LABELS), array_values(self::SUBJECT_LABELS))),
            ])
            ->emptyStateHeading('Nenhuma ação registrada ainda')
            ->emptyStateDescription('Criações, edições e exclusões manuais de ativos, lançamentos, contas, empresas e recorrências aparecem aqui.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditoria::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
