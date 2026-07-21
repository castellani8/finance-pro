<?php

namespace App\Filament\Pages\Widgets;

use App\Models\Tenant;
use App\Services\PassiveIncome;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PassiveIncomeStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();

        $series = PortfolioCache::remember(
            $tenant->getKey(),
            'passive-income.12',
            fn (): array => app(PassiveIncome::class)->monthly($tenant, 12),
        );

        $totals = $series['total'];
        $currentMonth = $totals === [] ? 0.0 : (float) end($totals);
        $average = $totals === [] ? 0.0 : array_sum($totals) / count($totals);
        $best = $totals === [] ? 0.0 : (float) max($totals);
        $goal = $tenant->passive_income_goal !== null ? (float) $tenant->passive_income_goal : null;

        $goalStat = $goal !== null
            ? Stat::make('Meta mensal', $this->money($goal))
                ->description($goal > 0
                    ? number_format(min(999, $currentMonth / $goal * 100), 1, ',', '.').'% atingido este mês'
                    : '—')
                ->descriptionIcon($currentMonth >= $goal ? 'heroicon-m-check-circle' : 'heroicon-m-flag')
                ->color($currentMonth >= $goal ? 'success' : 'warning')
            : Stat::make('Meta mensal', '—')
                ->description('Use "Definir meta mensal" para acompanhar seu "viver de renda".')
                ->color('gray');

        return [
            Stat::make('Renda passiva no mês', $this->money($currentMonth))
                ->description('Aluguéis + proventos + juros')
                ->color('success'),
            Stat::make('Média mensal (12m)', $this->money($average))
                ->description('Melhor mês: '.$this->money($best))
                ->color('gray'),
            $goalStat,
        ];
    }

    private function money(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
