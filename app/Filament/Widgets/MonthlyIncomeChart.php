<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Support\CompanyFilter;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyIncomeChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Proventos por mês';

    protected ?string $description = 'Dividendos, JCP, rendimentos, juros e amortizações recebidos.';

    protected static ?int $sort = 2;

    protected ?string $maxHeight = '300px';

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
        $companyId = CompanyFilter::normalize($this->pageFilters['company_id'] ?? null);

        return PortfolioCache::remember(
            $tenant->getKey(),
            "income.{$months}.".($companyId ?? 'all'),
            fn (): array => $this->buildData($tenant, $months, $companyId),
        );
    }

    private function buildData(Tenant $tenant, int $months, int|string|null $companyId): array
    {
        $start = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $byMonth = Transaction::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('type', Asset::CASH_INCOME_TYPES)
            ->whereNotNull('asset_id')
            ->when($companyId !== null, fn ($query) => $query->whereIn('asset_id', fn ($sub) => CompanyFilter::applyToCompanyColumn(
                $sub->select('id')->from('assets'),
                $companyId,
            )))
            ->where('transaction_date', '>=', $start->toDateString())
            ->get(['transaction_date', 'total_amount', 'direction', 'type'])
            ->groupBy(fn (Transaction $t): string => $t->transaction_date->format('Y-m'))
            ->map(fn ($group): float => (float) $group->sum(
                fn (Transaction $t) => ($t->isCredit() ? 1 : -1) * (float) $t->total_amount
            ));

        $labels = [];
        $data = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo(now())) {
            $labels[] = $cursor->locale('pt_BR')->translatedFormat('M/y');
            $data[] = round($byMonth[$cursor->format('Y-m')] ?? 0.0, 2);
            $cursor = $cursor->addMonthNoOverflow();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Proventos (R$)',
                    'data' => $data,
                    'backgroundColor' => '#22c55e',
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
