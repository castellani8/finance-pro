<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Services\CashFlow;
use App\Support\CompanyFilter;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Receitas x Despesas x Resultado, mês a mês — tudo que entra (proventos,
 * aluguéis, rendas avulsas) contra tudo que sai (despesas de ativos e da
 * empresa). Com $companyId setado, vira o fluxo de caixa de uma empresa
 * (usado na página de edição dela).
 */
class CashFlowChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Fluxo de caixa';

    protected ?string $description = 'Todas as receitas contra todas as despesas, com o resultado do mês.';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public ?string $filter = '12';

    public ?int $companyId = null;

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

        // Prioridade: escopo fixo (página da empresa) > filtro global do dashboard.
        $companyId = $this->companyId ?? CompanyFilter::normalize($this->pageFilters['company_id'] ?? null);

        $cacheKey = 'cashflow.'.$months.'.'.($companyId ?? 'all');

        $series = PortfolioCache::remember(
            $tenant->getKey(),
            $cacheKey,
            fn (): array => app(CashFlow::class)->monthly($tenant, $months, $companyId),
        );

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => 'Receitas',
                    'data' => $series['income'],
                    'backgroundColor' => '#22c55e',
                    'borderRadius' => 4,
                    'stack' => 'flow',
                ],
                [
                    'label' => 'Despesas',
                    'data' => array_map(fn (float $v): float => -$v, $series['expenses']),
                    'backgroundColor' => '#ef4444',
                    'borderRadius' => 4,
                    'stack' => 'flow',
                ],
                [
                    'label' => 'Resultado',
                    'data' => $series['result'],
                    'type' => 'line',
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'transparent',
                    'tension' => 0.3,
                    'pointRadius' => 2,
                    'stack' => 'result',
                ],
            ],
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
                'y' => ['stacked' => true],
            ],
        ];
    }
}
