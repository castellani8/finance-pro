<?php

namespace App\Filament\Pages\Widgets;

use App\Models\Tenant;
use App\Services\FinancialIndependence;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class IndependenceChart extends ChartWidget
{
    protected ?string $heading = 'Sua renda passiva ao longo dos anos';

    protected ?string $description = 'Projeção: patrimônio crescendo ao retorno esperado + aportes; renda passiva ao yield atual da sua carteira. O ponto em que a linha dourada cruza a vermelha é a sua independência.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public ?string $filter = '0';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            '0' => 'Plano atual',
            '500' => 'Aportando +R$ 500/mês',
            '1000' => 'Aportando +R$ 1.000/mês',
            '2000' => 'Aportando +R$ 2.000/mês',
        ];
    }

    protected function getData(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $extra = (float) ($this->filter ?? 0);

        $data = PortfolioCache::remember(
            $tenant->getKey(),
            'independence.chart.'.(int) $extra,
            fn (): array => app(FinancialIndependence::class)->build($tenant, $extra),
        );

        if (! $data['configurado']) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $data['series']['labels'],
            'datasets' => [
                [
                    'label' => 'Renda passiva projetada (mês)',
                    'data' => $data['series']['renda'],
                    'borderColor' => '#D4AF37',
                    'backgroundColor' => 'rgba(212, 175, 55, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Custo de vida (mês)',
                    'data' => $data['series']['custo'],
                    'borderColor' => '#ef4444',
                    'borderDash' => [6, 4],
                    'pointRadius' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
