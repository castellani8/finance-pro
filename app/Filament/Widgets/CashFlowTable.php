<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Services\CashFlow;
use App\Support\CompanyFilter;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Os números por trás do gráfico de fluxo de caixa: receitas, despesas,
 * resultado e acumulado, mês a mês — mesma fonte e mesmo cache do chart.
 */
class CashFlowTable extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.cash-flow-table';

    // Renderiza junto com a página: os dados já vêm do mesmo cache do gráfico,
    // e o carregamento lazy do dashboard não se dá bem com esta view.
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public string $months = '12';

    protected function getViewData(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $months = in_array($this->months, ['12', '24', '36'], true) ? (int) $this->months : 12;
        $companyId = CompanyFilter::normalize($this->pageFilters['company_id'] ?? null);

        // Mesma chave do CashFlowChart — um cálculo alimenta os dois widgets.
        $series = PortfolioCache::remember(
            $tenant->getKey(),
            'cashflow.'.$months.'.'.($companyId ?? 'all'),
            fn (): array => app(CashFlow::class)->monthly($tenant, $months, $companyId),
        );

        $rows = [];
        $acumulado = 0.0;

        foreach ($series['labels'] as $i => $label) {
            $acumulado += $series['result'][$i];

            $rows[] = [
                'mes' => $label,
                'receitas' => $series['income'][$i],
                'despesas' => $series['expenses'][$i],
                'resultado' => $series['result'][$i],
                'acumulado' => round($acumulado, 2),
            ];
        }

        return [
            // Mês mais recente primeiro — é o que se quer conferir.
            'rows' => array_reverse($rows),
            'totals' => [
                'receitas' => round(array_sum($series['income']), 2),
                'despesas' => round(array_sum($series['expenses']), 2),
                'resultado' => round(array_sum($series['result']), 2),
            ],
        ];
    }
}
