<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Widgets\IndependenceChart;
use App\Filament\Pages\Widgets\IndependenceStats;
use App\Support\PortfolioCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ViverDeRenda extends Page
{
    protected string $view = 'filament.pages.viver-de-renda';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $navigationLabel = 'Viver de Renda';

    protected static ?string $title = 'Quando posso viver de renda?';

    protected static ?int $navigationSort = 49;

    protected static string|\UnitEnum|null $navigationGroup = 'Planejamento';

    /** Visão da tabela de resultados: 'anual' ou 'mensal'. */
    public string $tableMode = 'anual';

    /** Projeção completa (cacheada) — alimenta a tabela ano a ano da view. */
    public function getProjection(): array
    {
        $tenant = Filament::getTenant();

        return \App\Support\PortfolioCache::remember(
            $tenant->getKey(),
            'independence.base',
            fn (): array => app(\App\Services\FinancialIndependence::class)->build($tenant),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configurarPlano')
                ->label('Definir meu plano')
                ->icon('heroicon-o-adjustments-horizontal')
                ->modalHeading('Meu plano de independência')
                ->modalDescription('A projeção usa o seu patrimônio e a sua renda passiva reais — estes três números são as únicas suposições.')
                ->schema([
                    TextInput::make('custo')
                        ->label('Custo de vida mensal')
                        ->helperText('Quanto a sua renda passiva precisa cobrir por mês.')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('R$')
                        ->default(fn (): ?float => Filament::getTenant()->independence_monthly_cost !== null
                            ? (float) Filament::getTenant()->independence_monthly_cost
                            : null)
                        ->required(),
                    TextInput::make('aporte')
                        ->label('Aporte mensal planejado')
                        ->helperText('Quanto você pretende investir por mês daqui para frente.')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('R$')
                        ->default(fn (): float => (float) (Filament::getTenant()->independence_monthly_contribution ?? 0)),
                    TextInput::make('reajuste')
                        ->label('Reajuste anual do aporte')
                        ->helperText('Quanto o aporte cresce por ano (aumento salarial, dissídio...). Ex: 5 para +5% ao ano.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->suffix('% a.a.')
                        ->default(fn (): float => (float) (Filament::getTenant()->independence_contribution_growth ?? 0)),
                    TextInput::make('retorno')
                        ->label('Retorno esperado')
                        ->helperText('Ao ano. Se informar a inflação abaixo, use o retorno cheio (nominal); senão, use um retorno já acima da inflação.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(30)
                        ->suffix('% a.a.')
                        ->default(fn (): float => (float) (Filament::getTenant()->independence_expected_return ?? 8.0)),
                    TextInput::make('inflacao')
                        ->label('Inflação média anual')
                        ->helperText('O custo de vida sobe com ela na projeção. Deixe 0 para pensar tudo em valores de hoje.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(30)
                        ->suffix('% a.a.')
                        ->default(fn (): float => (float) (Filament::getTenant()->independence_inflation ?? 0)),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();

                    $tenant->forceFill([
                        'independence_monthly_cost' => (float) $data['custo'] > 0 ? (float) $data['custo'] : null,
                        'independence_monthly_contribution' => (float) ($data['aporte'] ?? 0),
                        'independence_contribution_growth' => (float) ($data['reajuste'] ?? 0),
                        'independence_expected_return' => (float) ($data['retorno'] ?? 8.0),
                        'independence_inflation' => (float) ($data['inflacao'] ?? 0),
                    ])->save();

                    PortfolioCache::bump($tenant->getKey());

                    Notification::make()
                        ->title('Plano salvo — projeção atualizada')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IndependenceStats::class,
            IndependenceChart::class,
        ];
    }
}
