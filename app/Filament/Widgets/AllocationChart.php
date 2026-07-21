<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Tenant;
use App\Support\CompanyFilter;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AllocationChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Alocação da carteira';

    protected ?string $description = 'Distribuição do valor atual por classe de ativo.';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '300px';

    private const TYPE_COLORS = [
        'STOCK' => '#22c55e',
        'FII' => '#3b82f6',
        'FIXED_INCOME' => '#f59e0b',
        'OPTION' => '#ef4444',
        'VEHICLE' => '#8b5cf6',
        'MACHINERY' => '#a855f7',
        'REAL_ESTATE' => '#0ea5e9',
        'COMMODITY' => '#eab308',
        'COLLECTIBLE' => '#ec4899',
        'SOFTWARE' => '#14b8a6',
        'CASH' => '#64748b',
        'OTHER' => '#9ca3af',
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $companyId = CompanyFilter::normalize($this->pageFilters['company_id'] ?? null);

        return PortfolioCache::remember(
            $tenant->getKey(),
            'allocation.'.($companyId ?? 'all'),
            fn (): array => $this->buildData($tenant, $companyId),
        );
    }

    private function buildData(Tenant $tenant, int|string|null $companyId): array
    {
        $totals = CompanyFilter::applyToCompanyColumn(
            Asset::query()->where('tenant_id', $tenant->getKey()),
            $companyId,
        )
            ->wherePositionPositive()
            ->with('transactions')
            ->get()
            ->tap(fn ($assets) => Asset::primeMarketData($assets))
            ->groupBy('type')
            ->map(fn ($assets): float => round(
                (float) $assets->sum(fn (Asset $asset) => $asset->currentValue()),
                2,
            ))
            ->filter(fn (float $total): bool => $total > 0)
            ->sortDesc();

        // Saldo em contas de dinheiro entra como a fatia "Caixa".
        $cash = (float) CompanyFilter::applyToCompanyColumn(
            Account::query()->where('tenant_id', $tenant->getKey()),
            $companyId,
        )->with('transactions')->get()->sum(fn (Account $account): float => $account->balanceInBrlAt());

        if ($cash > 0.005) {
            $totals->put('CASH', round($cash, 2));
        }

        return [
            'labels' => $totals->keys()
                ->map(fn (string $type): string => $type === 'CASH' ? 'Caixa / Contas' : (Asset::TYPE_LABELS[$type] ?? $type))
                ->all(),
            'datasets' => [
                [
                    'data' => $totals->values()->all(),
                    'backgroundColor' => $totals->keys()
                        ->map(fn (string $type): string => self::TYPE_COLORS[$type] ?? '#9ca3af')
                        ->all(),
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
