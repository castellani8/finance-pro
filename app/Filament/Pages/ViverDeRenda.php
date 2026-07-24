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
                    TextInput::make('retorno')
                        ->label('Retorno real esperado')
                        ->helperText('Ao ano, já descontada a inflação. Um valor conservador ajuda a não se enganar.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(30)
                        ->suffix('% a.a.')
                        ->default(fn (): float => (float) (Filament::getTenant()->independence_expected_return ?? 8.0)),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();

                    $tenant->forceFill([
                        'independence_monthly_cost' => (float) $data['custo'] > 0 ? (float) $data['custo'] : null,
                        'independence_monthly_contribution' => (float) ($data['aporte'] ?? 0),
                        'independence_expected_return' => (float) ($data['retorno'] ?? 8.0),
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
