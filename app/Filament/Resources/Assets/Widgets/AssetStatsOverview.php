<?php

namespace App\Filament\Resources\Assets\Widgets;

use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Análise financeira do ativo na tela de edição: valor de compra, custo total,
 * valor atual, renda, despesas e resultado — com rótulos adequados à classe
 * (físico x investimento).
 */
class AssetStatsOverview extends StatsOverviewWidget
{
    public ?Asset $record = null;

    protected function getStats(): array
    {
        $asset = $this->record?->load('transactions');

        if ($asset === null) {
            return [];
        }

        $acquisition = $asset->acquisitionValue();
        $invested = $asset->purchaseValue();
        $current = $asset->currentValue();
        $income = $asset->dividendsReceived();
        $expenses = $asset->expensesTotal();
        $result = $asset->netResult();
        $changePct = $asset->percentChangeWithDividends();

        if ($asset->isPhysical()) {
            $rate = $asset->depreciationRate();

            return [
                Stat::make('Valor de compra', $this->money($acquisition))
                    ->description('Só a aquisição — não muda com despesas')
                    ->color('gray'),
                Stat::make('Custo total investido', $this->money($invested))
                    ->description('Compra + benfeitorias + despesas')
                    ->color('gray'),
                Stat::make('Valor atual', $this->money($current))
                    ->description($rate > 0 ? "Depreciando {$this->percent($rate)} a.a." : 'Sem depreciação configurada')
                    ->descriptionIcon($current >= $acquisition ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->color($current >= $acquisition ? 'success' : 'warning'),
                Stat::make('Renda recebida', $this->money($income))
                    ->description($this->money($asset->incomeLastTwelveMonths()).' nos últimos 12 meses')
                    ->color('success'),
                Stat::make('Despesas', $this->money($expenses))
                    ->description($this->money($asset->expensesLastTwelveMonths()).' nos últimos 12 meses')
                    ->color('danger'),
                Stat::make('Resultado', ($result >= 0 ? '+' : '-').$this->money(abs($result)))
                    ->description($result >= 0 ? 'Este bem gera renda' : 'Este bem está custando dinheiro')
                    ->descriptionIcon($result >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                    ->color($result >= 0 ? 'success' : 'danger'),
            ];
        }

        return [
            Stat::make('Valor investido', $this->money($invested))
                ->description('Custo da posição atual')
                ->color('gray'),
            Stat::make('Valor atual', $this->money($current))
                ->description($invested > 0 ? $this->signedPercent(($current - $invested) / $invested * 100).' sem proventos' : '—')
                ->descriptionIcon($current >= $invested ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($current >= $invested ? 'success' : 'danger'),
            Stat::make('Proventos recebidos', $this->money($income))
                ->description($changePct !== null ? $this->signedPercent($changePct).' com proventos' : '—')
                ->descriptionIcon(($changePct ?? 0) >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color(($changePct ?? 0) >= 0 ? 'success' : 'danger'),
        ];
    }

    private function money(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').'%';
    }

    private function signedPercent(float $value): string
    {
        return sprintf('%s%s%%', $value >= 0 ? '+' : '', number_format($value, 2, ',', '.'));
    }
}
