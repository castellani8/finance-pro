<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Tenant;

/**
 * Projeção de independência financeira ("Viver de Renda"): a partir do
 * patrimônio atual, da renda passiva observada e do plano do tenant (custo de
 * vida, aporte mensal e retorno esperado), estima quando a renda passiva
 * projetada passa a cobrir o custo de vida.
 *
 * Modelo assumido (simples de propósito): o patrimônio cresce ao retorno
 * esperado + aportes; a renda passiva projetada é o patrimônio vezes o yield
 * mensal observado da própria carteira (fallback: o retorno esperado).
 */
class FinancialIndependence
{
    /** Horizonte máximo da projeção, em meses (50 anos). */
    private const MAX_MONTHS = 600;

    public function __construct(private PassiveIncome $passiveIncome) {}

    /**
     * @return array{
     *     configurado: bool, custo_mensal: ?float, aporte_mensal: float, retorno_anual: float,
     *     patrimonio_atual: float, renda_media_mensal: float, cobertura_pct: ?float,
     *     patrimonio_alvo: ?float, meses_ate_independencia: ?int, data_independencia: ?string,
     *     series: array{labels: array<int, string>, renda: array<int, float>, custo: array<int, float>, patrimonio: array<int, float>}
     * }
     */
    public function build(Tenant $tenant, ?float $extraContribution = null): array
    {
        $custo = $tenant->independence_monthly_cost !== null ? (float) $tenant->independence_monthly_cost : null;
        $aporte = max(0.0, (float) ($tenant->independence_monthly_contribution ?? 0) + (float) ($extraContribution ?? 0));
        $retornoAnual = (float) ($tenant->independence_expected_return ?? 8.0);

        $patrimonio = $this->currentNetWorth($tenant);
        $rendaMedia = $this->averagePassiveIncome($tenant);

        // Taxa mensal equivalente ao retorno anual esperado.
        $crescimentoMensal = (1 + $retornoAnual / 100) ** (1 / 12) - 1;

        // Yield observado da carteira; sem histórico, usa o retorno esperado.
        $yieldMensal = ($patrimonio > 0 && $rendaMedia > 0)
            ? $rendaMedia / $patrimonio
            : $crescimentoMensal;

        $mesesAte = null;
        $labels = $renda = $custoSerie = $patrimonioSerie = [];

        if ($custo !== null && $custo > 0 && $yieldMensal > 0) {
            $pat = $patrimonio;

            for ($mes = 0; $mes <= self::MAX_MONTHS; $mes++) {
                if ($mes > 0) {
                    $pat = $pat * (1 + $crescimentoMensal) + $aporte;
                }

                $rendaProjetada = $pat * $yieldMensal;

                if ($mesesAte === null && $rendaProjetada >= $custo) {
                    $mesesAte = $mes;
                }

                // Um ponto por ano no gráfico, do ano atual em diante.
                if ($mes % 12 === 0) {
                    $labels[] = (string) (now()->year + intdiv($mes, 12));
                    $renda[] = round($rendaProjetada, 2);
                    $custoSerie[] = round($custo, 2);
                    $patrimonioSerie[] = round($pat, 2);
                }

                // Depois do marco, mostra mais 2 anos e para (mínimo 10 anos de curva).
                if ($mesesAte !== null && $mes >= max(120, $mesesAte + 24)) {
                    break;
                }
            }
        }

        return [
            'configurado' => $custo !== null && $custo > 0,
            'custo_mensal' => $custo,
            'aporte_mensal' => $aporte,
            'retorno_anual' => $retornoAnual,
            'patrimonio_atual' => round($patrimonio, 2),
            'renda_media_mensal' => round($rendaMedia, 2),
            'cobertura_pct' => ($custo !== null && $custo > 0) ? round($rendaMedia / $custo * 100, 1) : null,
            'patrimonio_alvo' => ($custo !== null && $custo > 0 && $yieldMensal > 0) ? round($custo / $yieldMensal, 2) : null,
            'meses_ate_independencia' => $mesesAte,
            'data_independencia' => $mesesAte !== null
                ? now()->addMonths($mesesAte)->locale('pt_BR')->translatedFormat('M/Y')
                : null,
            'series' => [
                'labels' => $labels,
                'renda' => $renda,
                'custo' => $custoSerie,
                'patrimonio' => $patrimonioSerie,
            ],
        ];
    }

    /** Patrimônio total atual: ativos a mercado + saldos de conta, em BRL. */
    private function currentNetWorth(Tenant $tenant): float
    {
        $assets = Asset::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('transactions')
            ->get();

        Asset::primeMarketData($assets);

        $accounts = Account::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('transactions')
            ->get();

        return $assets->sum(fn (Asset $asset): float => $asset->currentValue())
            + $accounts->sum(fn (Account $account): float => $account->balanceInBrlAt());
    }

    /** Média mensal de renda passiva dos últimos 12 meses. */
    private function averagePassiveIncome(Tenant $tenant): float
    {
        $series = $this->passiveIncome->monthly($tenant, 12);
        $totais = $series['total'] ?? [];

        return $totais === [] ? 0.0 : array_sum($totais) / count($totais);
    }
}
