<?php

namespace App\Filament\Pages\Widgets;

use App\Models\Tenant;
use App\Services\FinancialIndependence;
use App\Support\PortfolioCache;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IndependenceStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();

        $data = PortfolioCache::remember(
            $tenant->getKey(),
            'independence.base',
            fn (): array => app(FinancialIndependence::class)->build($tenant),
        );

        if (! $data['configurado']) {
            return [
                Stat::make('Renda passiva média (12m)', $this->money($data['renda_media_mensal']))
                    ->description('É ela que um dia vai pagar suas contas')
                    ->color('success'),
                Stat::make('Patrimônio atual', $this->money($data['patrimonio_atual'])),
                Stat::make('Independência projetada', '—')
                    ->description('Clique em "Definir meu plano" para ver sua data')
                    ->descriptionIcon('heroicon-m-adjustments-horizontal')
                    ->color('warning'),
            ];
        }

        $cobertura = min(999.0, (float) $data['cobertura_pct']);

        $independencia = match (true) {
            $data['meses_ate_independencia'] === 0 => 'Você já chegou! 🎉',
            $data['data_independencia'] !== null => $data['data_independencia'],
            default => 'Além de 50 anos',
        };

        $descIndependencia = match (true) {
            $data['meses_ate_independencia'] === 0 => 'Sua renda passiva já cobre o custo de vida',
            $data['meses_ate_independencia'] !== null => 'Em '.$this->humanMonths($data['meses_ate_independencia']).', no ritmo do plano',
            default => 'Aumente o aporte para encurtar o caminho',
        };

        return [
            Stat::make('Cobertura do custo de vida', number_format($cobertura, 1, ',', '.').'%')
                ->description($this->money($data['renda_media_mensal']).' de renda média vs '.$this->money($data['custo_mensal']).' de custo')
                ->descriptionIcon($cobertura >= 100 ? 'heroicon-m-check-circle' : 'heroicon-m-arrow-trending-up')
                ->color($cobertura >= 100 ? 'success' : ($cobertura >= 50 ? 'warning' : 'gray')),
            Stat::make('Independência projetada', $independencia)
                ->description($descIndependencia)
                ->descriptionIcon('heroicon-m-rocket-launch')
                ->color($data['meses_ate_independencia'] !== null ? 'success' : 'gray'),
            Stat::make('Patrimônio alvo', $data['patrimonio_alvo'] !== null ? $this->money($data['patrimonio_alvo']) : '—')
                ->description('Você já tem '.$this->money($data['patrimonio_atual']))
                ->color('primary'),
        ];
    }

    private function money(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    private function humanMonths(int $months): string
    {
        $years = intdiv($months, 12);
        $rest = $months % 12;

        return match (true) {
            $months < 12 => "{$months} mes".($months === 1 ? '' : 'es'),
            $rest === 0 => "{$years} ano".($years === 1 ? '' : 's'),
            default => "{$years} ano".($years === 1 ? '' : 's')." e {$rest} mes".($rest === 1 ? '' : 'es'),
        };
    }
}
