<?php

namespace App\Filament\Resources\Assets\Widgets;

use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssetsPortfolioOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListAssets::class;
    }

    protected function getStats(): array
    {
        $assets = $this->getPageTableQuery()->with('transactions')->get();
        Asset::primeMarketData($assets);

        $invested = $assets->sum(fn (Asset $a) => $a->purchaseValue());
        $current = $assets->sum(fn (Asset $a) => $a->currentValue());
        $dividends = $assets->sum(fn (Asset $a) => $a->dividendsReceived());

        $pct = $invested > 0 ? ($current - $invested) / $invested * 100 : 0.0;
        $pctWithDividends = $invested > 0 ? ($current + $dividends - $invested) / $invested * 100 : 0.0;

        return [
            Stat::make('Valor investido', $this->money($invested))
                ->description('Custo da posição atual')
                ->color('gray'),

            Stat::make('Valor atual (bruto)', $this->money($current))
                ->description($this->signedPercent($pct).' sem proventos')
                ->descriptionIcon($pct >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($pct >= 0 ? 'success' : 'danger'),

            Stat::make('Proventos recebidos', $this->money($dividends))
                ->description($this->signedPercent($pctWithDividends).' com proventos')
                ->descriptionIcon($pctWithDividends >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($pctWithDividends >= 0 ? 'success' : 'danger'),
        ];
    }

    private function money(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    private function signedPercent(float $value): string
    {
        return sprintf('%s%s%%', $value >= 0 ? '+' : '', number_format($value, 2, ',', '.'));
    }
}
