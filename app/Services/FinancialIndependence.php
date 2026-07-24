<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Projeção de independência financeira ("Viver de Renda"): a partir do
 * patrimônio atual, da renda passiva observada e do plano do tenant (custo de
 * vida, aporte mensal, reajuste anual do aporte, retorno esperado e inflação
 * média), estima quando a renda passiva projetada passa a cobrir o custo de
 * vida — que também sobe com a inflação.
 *
 * Modelo assumido (o mesmo de uma calculadora de juros compostos, com camada
 * de aposentadoria por cima): o patrimônio rende ao retorno esperado e recebe
 * aportes reajustados uma vez por ano; a renda passiva projetada é o
 * patrimônio vezes o yield mensal observado da carteira (fallback: o retorno
 * esperado); o custo de vida é corrigido pela inflação.
 */
class FinancialIndependence
{
    /** Horizonte máximo da projeção, em meses (50 anos). */
    private const MAX_MONTHS = 600;

    public function __construct(private PassiveIncome $passiveIncome) {}

    /**
     * @return array{
     *     configurado: bool, custo_mensal: ?float, aporte_mensal: float, retorno_anual: float,
     *     reajuste_aporte_anual: float, inflacao_anual: float,
     *     patrimonio_atual: float, renda_media_mensal: float, cobertura_pct: ?float,
     *     patrimonio_alvo: ?float, meses_ate_independencia: ?int, data_independencia: ?string,
     *     series: array{labels: array<int, string>, renda: array<int, float>, custo: array<int, float>, patrimonio: array<int, float>},
     *     table_anual: array<int, array<string, mixed>>, table_mensal: array<int, array<string, mixed>>,
     *     resumo: ?array{patrimonio: float, total_investido: float, total_juros: float, horizonte_anos: int}
     * }
     */
    public function build(Tenant $tenant, ?float $extraContribution = null): array
    {
        $custo = $tenant->independence_monthly_cost !== null ? (float) $tenant->independence_monthly_cost : null;
        $aporteInicial = max(0.0, (float) ($tenant->independence_monthly_contribution ?? 0) + (float) ($extraContribution ?? 0));
        $retornoAnual = (float) ($tenant->independence_expected_return ?? 8.0);
        $reajusteAnual = (float) ($tenant->independence_contribution_growth ?? 0.0);
        $inflacaoAnual = (float) ($tenant->independence_inflation ?? 0.0);

        $patrimonioInicial = $this->currentNetWorth($tenant);
        $rendaMedia = $this->averagePassiveIncome($tenant);

        // Taxas mensais equivalentes às anuais informadas.
        $crescimentoMensal = (1 + $retornoAnual / 100) ** (1 / 12) - 1;
        $inflacaoMensal = (1 + $inflacaoAnual / 100) ** (1 / 12) - 1;

        // Yield observado da carteira; sem histórico, usa o retorno esperado.
        $yieldMensal = ($patrimonioInicial > 0 && $rendaMedia > 0)
            ? $rendaMedia / $patrimonioInicial
            : $crescimentoMensal;

        $mesesAte = null;
        $labels = $renda = $custoSerie = $patrimonioSerie = [];
        $tableAnual = $tableMensal = [];
        $resumo = null;

        if ($custo !== null && $custo > 0 && $yieldMensal > 0) {
            $pat = $patrimonioInicial;
            $aporteMensal = $aporteInicial;
            $custoCorrigido = $custo;
            $aportadoAcumulado = 0.0;
            $jurosAcumulados = 0.0;
            $jurosNoAno = 0.0;
            $inicio = now()->startOfMonth();

            for ($mes = 0; $mes <= self::MAX_MONTHS; $mes++) {
                if ($mes > 0) {
                    // O aporte é reajustado a cada aniversário do plano.
                    if ($mes % 12 === 1 && $mes > 12) {
                        $aporteMensal *= 1 + $reajusteAnual / 100;
                    }

                    $jurosDoMes = $pat * $crescimentoMensal;
                    $pat += $jurosDoMes + $aporteMensal;
                    $aportadoAcumulado += $aporteMensal;
                    $jurosAcumulados += $jurosDoMes;
                    $jurosNoAno += $jurosDoMes;
                    $custoCorrigido *= 1 + $inflacaoMensal;

                    $tableMensal[] = [
                        'mes' => str(
                            $inicio->copy()->addMonths($mes)->locale('pt_BR')->translatedFormat('M/Y')
                        )->replace('.', '')->toString(),
                        'aporte' => round($aporteMensal, 2),
                        'juros' => round($jurosDoMes, 2),
                        'total_investido' => round($patrimonioInicial + $aportadoAcumulado, 2),
                        'total_juros' => round($jurosAcumulados, 2),
                        'patrimonio' => round($pat, 2),
                        'renda_mensal' => round($pat * $yieldMensal, 2),
                        'cobertura_pct' => round($pat * $yieldMensal / $custoCorrigido * 100, 1),
                    ];
                }

                $rendaProjetada = $pat * $yieldMensal;

                if ($mesesAte === null && $rendaProjetada >= $custoCorrigido) {
                    $mesesAte = $mes;
                }

                // Um ponto por ano no gráfico e uma linha por ano na tabela.
                if ($mes % 12 === 0) {
                    $ano = now()->year + intdiv($mes, 12);

                    $labels[] = (string) $ano;
                    $renda[] = round($rendaProjetada, 2);
                    $custoSerie[] = round($custoCorrigido, 2);
                    $patrimonioSerie[] = round($pat, 2);

                    $tableAnual[] = [
                        'ano' => $ano,
                        'aporte_mensal' => round($aporteMensal, 2),
                        'juros_no_ano' => round($jurosNoAno, 2),
                        'total_investido' => round($patrimonioInicial + $aportadoAcumulado, 2),
                        'total_juros' => round($jurosAcumulados, 2),
                        'patrimonio' => round($pat, 2),
                        'renda_mensal' => round($rendaProjetada, 2),
                        'custo_mensal' => round($custoCorrigido, 2),
                        'cobertura_pct' => round($rendaProjetada / $custoCorrigido * 100, 1),
                    ];

                    $jurosNoAno = 0.0;
                }

                // Depois do marco, mostra mais 2 anos e para (mínimo 10 anos de curva).
                if ($mesesAte !== null && $mes >= max(120, $mesesAte + 24)) {
                    break;
                }
            }

            $resumo = [
                'patrimonio' => round($pat, 2),
                'total_investido' => round($patrimonioInicial + $aportadoAcumulado, 2),
                'total_juros' => round($jurosAcumulados, 2),
                'horizonte_anos' => intdiv(count($tableMensal), 12),
            ];
        }

        return [
            'configurado' => $custo !== null && $custo > 0,
            'custo_mensal' => $custo,
            'aporte_mensal' => $aporteInicial,
            'retorno_anual' => $retornoAnual,
            'reajuste_aporte_anual' => $reajusteAnual,
            'inflacao_anual' => $inflacaoAnual,
            'patrimonio_atual' => round($patrimonioInicial, 2),
            'renda_media_mensal' => round($rendaMedia, 2),
            'cobertura_pct' => ($custo !== null && $custo > 0) ? round($rendaMedia / $custo * 100, 1) : null,
            // Em valores de hoje: quanto de patrimônio gera o custo de vida atual.
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
            'table_anual' => $tableAnual,
            'table_mensal' => $tableMensal,
            'resumo' => $resumo,
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
