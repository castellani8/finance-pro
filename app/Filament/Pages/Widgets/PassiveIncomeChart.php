<?php

namespace App\Filament\Pages\Widgets;

use App\Models\Tenant;
use App\Services\PassiveIncome;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class PassiveIncomeChart extends ChartWidget
{
    protected ?string $heading = 'Renda passiva por mês';

    protected ?string $description = 'Aluguéis e rendas de bens + proventos da bolsa + juros de renda fixa, contra a meta.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public ?string $filter = '12';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return [
            '12' => 'Últimos 12 meses',
            '24' => 'Últimos 2 anos',
            '36' => 'Últimos 3 anos',
        ];
    }

    protected function getData(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $months = (int) ($this->filter ?? 12);

        $series = PortfolioCache::remember(
            $tenant->getKey(),
            "passive-income.{$months}",
            fn (): array => app(PassiveIncome::class)->monthly($tenant, $months),
        );

        $goal = $tenant->passive_income_goal !== null ? (float) $tenant->passive_income_goal : null;

        $datasets = [
            [
                'label' => 'Aluguéis / bens',
                'data' => $series['alugueis'],
                'backgroundColor' => '#8b5cf6',
                'stack' => 'renda',
                'borderRadius' => 3,
            ],
            [
                'label' => 'Proventos (bolsa)',
                'data' => $series['proventos'],
                'backgroundColor' => '#22c55e',
                'stack' => 'renda',
                'borderRadius' => 3,
            ],
            [
                'label' => 'Juros (renda fixa)',
                'data' => $series['juros'],
                'backgroundColor' => '#f59e0b',
                'stack' => 'renda',
                'borderRadius' => 3,
            ],
        ];

        if ($goal !== null && $goal > 0) {
            $datasets[] = [
                'label' => 'Meta mensal',
                'data' => array_fill(0, count($series['labels']), $goal),
                'type' => 'line',
                'borderColor' => '#ef4444',
                'backgroundColor' => 'transparent',
                'borderDash' => [6, 4],
                'pointRadius' => 0,
                'stack' => 'meta',
            ];
        }

        return [
            'labels' => $series['labels'],
            'datasets' => $datasets,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true],
            ],
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true],
            ],
        ];
    }
}
