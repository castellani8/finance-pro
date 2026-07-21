<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Services\PortfolioEvolution;
use App\Support\CompanyFilter;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PortfolioEvolutionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Evolução do patrimônio';

    protected ?string $description = 'Valor investido x valor de mercado, com os mesmos aportes rendendo 100% do CDI e acompanhando o IBOV.';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public ?string $filter = '12';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            '30d' => 'Últimos 30 dias (diário)',
            '6' => 'Últimos 6 meses',
            '12' => 'Últimos 12 meses',
            '24' => 'Últimos 2 anos',
            '36' => 'Últimos 3 anos',
            'all' => 'Desde o início',
        ];
    }

    protected function getData(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $filter = $this->filter ?? '12';
        $companyId = CompanyFilter::normalize($this->pageFilters['company_id'] ?? null);

        $cacheKey = "evolution.{$filter}.".($companyId ?? 'all');

        $series = PortfolioCache::remember($tenant->getKey(), $cacheKey, function () use ($tenant, $filter, $companyId): array {
            $evolution = app(PortfolioEvolution::class);

            return match ($filter) {
                '30d' => $evolution->dailySeries($tenant, 30, companyId: $companyId),
                'all' => $evolution->monthlySeries($tenant, null, $companyId),
                default => $evolution->monthlySeries($tenant, (int) $filter, $companyId),
            };
        });

        return [
            'labels' => $series['labels'],
            // Benchmarks vêm vazios quando o recorte só tem bens físicos;
            // datasets sem dados são omitidos do gráfico.
            'datasets' => array_values(array_filter([
                [
                    'label' => 'Valor de mercado',
                    'data' => $series['current'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 2,
                ],
                [
                    'label' => 'Valor investido',
                    'data' => $series['invested'],
                    'borderColor' => '#9ca3af',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [6, 4],
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 2,
                ],
                [
                    'label' => 'Aportes a 100% do CDI',
                    'data' => $series['cdi'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [2, 3],
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Aportes no IBOV',
                    'data' => $series['ibov'],
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [2, 3],
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                    'hidden' => true,
                ],
            ], fn (array $dataset): bool => $dataset['data'] !== [])),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
