<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Widgets\PassiveIncomeChart;
use App\Filament\Pages\Widgets\PassiveIncomeStats;
use App\Support\PortfolioCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Painel "viver de renda": aluguéis + proventos + juros consolidados mês a
 * mês, com meta mensal de renda passiva definida pelo usuário.
 */
class RendaPassiva extends Page
{
    protected string $view = 'filament.pages.renda-passiva';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Planejamento';

    protected static ?string $navigationLabel = 'Renda Passiva';

    protected static ?string $title = 'Renda Passiva';

    protected static ?int $navigationSort = 48;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setGoal')
                ->label('Definir meta mensal')
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->modalHeading('Meta mensal de renda passiva')
                ->modalDescription('Quanto você quer receber por mês sem trabalhar? A meta aparece no gráfico e no progresso do mês.')
                ->schema([
                    TextInput::make('goal')
                        ->label('Meta mensal')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('R$')
                        ->default(fn (): ?float => Filament::getTenant()->passive_income_goal !== null
                            ? (float) Filament::getTenant()->passive_income_goal
                            : null)
                        ->helperText('Deixe 0 para remover a meta.'),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();
                    $goal = (float) ($data['goal'] ?? 0);

                    $tenant->forceFill(['passive_income_goal' => $goal > 0 ? $goal : null])->save();
                    PortfolioCache::bump($tenant->getKey());

                    Notification::make()
                        ->title($goal > 0 ? 'Meta definida' : 'Meta removida')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PassiveIncomeStats::class,
            PassiveIncomeChart::class,
        ];
    }
}
