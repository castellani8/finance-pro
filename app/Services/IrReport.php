<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Relatório anual para a declaração de IRPF: bens e direitos (posição e custo
 * em 31/12), proventos do ano por natureza tributária e vendas mês a mês
 * (para conferir a isenção de R$ 20 mil em ações).
 *
 * Os valores seguem o custo por preço médio calculado pela plataforma;
 * confira sempre com os informes de rendimentos das corretoras.
 */
class IrReport
{
    /** Tipos isentos (dividendos e rendimentos de FII). */
    private const EXEMPT_TYPES = ['DIVIDEND', 'INCOME'];

    /** Tipos com tributação exclusiva/definitiva na fonte (JCP, juros de renda fixa). */
    private const EXCLUSIVE_TYPES = ['JCP', 'INTEREST'];

    /**
     * @return array{
     *     year: int,
     *     bens: array<int, array<string, mixed>>,
     *     proventos: array<int, array<string, mixed>>,
     *     vendas: array<int, array<string, mixed>>,
     *     totais: array<string, float>
     * }
     */
    public function build(Tenant $tenant, int $year): array
    {
        $assets = Asset::query()
            ->where('tenant_id', $tenant->getKey())
            ->with(['transactions', 'company'])
            ->orderBy('name')
            ->get();

        $accounts = Account::query()
            ->where('tenant_id', $tenant->getKey())
            ->with('transactions')
            ->orderBy('name')
            ->get();

        $bens = [...$this->bensEDireitos($assets, $year), ...$this->contasEmBens($accounts, $year)];
        $proventos = $this->proventosDoAno($assets, $year);
        $vendas = $this->vendasPorMes($assets, $year);
        $ganhos = $this->ganhosDeCapital($assets, $year);

        return [
            'year' => $year,
            'bens' => $bens,
            'proventos' => $proventos,
            'vendas' => $vendas,
            'ganhos' => $ganhos,
            'totais' => [
                'custo_anterior' => round(array_sum(array_column($bens, 'custo_anterior')), 2),
                'custo_atual' => round(array_sum(array_column($bens, 'custo_atual')), 2),
                'isentos' => round(array_sum(array_column($proventos, 'isentos')), 2),
                'exclusivos' => round(array_sum(array_column($proventos, 'exclusivos')), 2),
                'outros' => round(array_sum(array_column($proventos, 'outros')), 2),
                'darf' => round(array_sum(array_column($ganhos, 'darf')), 2),
            ],
        ];
    }

    /**
     * Saldos em contas de dinheiro em 31/12 (grupo 06 da declaração).
     *
     * @param  Collection<int, Account>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function contasEmBens(Collection $accounts, int $year): array
    {
        $end = "{$year}-12-31";
        $previousEnd = ($year - 1).'-12-31';
        $rows = [];

        foreach ($accounts as $account) {
            $previous = max(0.0, $account->balanceInBrlAt($previousEnd));
            $current = max(0.0, $account->balanceInBrlAt($end));

            if ($previous <= 0.005 && $current <= 0.005) {
                continue;
            }

            $rows[] = [
                'ticker' => null,
                'nome' => $account->name,
                'tipo' => 'ACCOUNT',
                'grupo_codigo' => '06 / 01 — Depósito em conta',
                'quantidade' => 1.0,
                'custo_anterior' => round($previous, 2),
                'custo_atual' => round($current, 2),
                'discriminacao' => sprintf(
                    'Saldo em %s (%s) em 31/12/%d: R$ %s.',
                    $account->name,
                    Account::KIND_LABELS[$account->kind] ?? $account->kind,
                    $year,
                    number_format($current, 2, ',', '.'),
                ),
            ];
        }

        return $rows;
    }

    /**
     * Apuração simplificada de ganho de capital em renda variável, mês a mês:
     * ações têm isenção quando as vendas do mês ficam até R$ 20 mil (15% sobre
     * o ganho acima disso); FIIs pagam 20% sempre; opções pagam 15% sem
     * qualquer isenção; prejuízos compensam ganhos dos meses seguintes da
     * mesma classe. Operações day-trade não são separadas.
     *
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function ganhosDeCapital(Collection $assets, int $year): array
    {
        $classes = [
            'acoes' => ['types' => ['STOCK'], 'rate' => 0.15, 'exemption' => 20000.0],
            'fiis' => ['types' => ['FII'], 'rate' => 0.20, 'exemption' => 0.0],
            'opcoes' => ['types' => ['OPTION'], 'rate' => 0.15, 'exemption' => 0.0],
        ];

        $monthly = [];

        foreach ($classes as $classKey => $config) {
            foreach ($assets->whereIn('type', $config['types']) as $asset) {
                foreach ($asset->realizedSalesForYear($year) as $sale) {
                    $month = (int) substr($sale['date'], 5, 2);
                    $monthly[$month][$classKey]['vendas'] = ($monthly[$month][$classKey]['vendas'] ?? 0.0) + $sale['proceeds'];
                    $monthly[$month][$classKey]['ganho'] = ($monthly[$month][$classKey]['ganho'] ?? 0.0) + $sale['gain'];
                }
            }
        }

        ksort($monthly);

        $carryLoss = ['acoes' => 0.0, 'fiis' => 0.0, 'opcoes' => 0.0];
        $rows = [];

        foreach ($monthly as $month => $byClass) {
            $row = ['mes' => $month, 'darf' => 0.0];

            foreach ($classes as $classKey => $config) {
                $sales = round($byClass[$classKey]['vendas'] ?? 0.0, 2);
                $gain = round($byClass[$classKey]['ganho'] ?? 0.0, 2);
                $exempt = $classKey === 'acoes' && $sales <= $config['exemption'];
                $tax = 0.0;

                if ($gain < 0) {
                    // Prejuízo acumula para compensar meses seguintes.
                    $carryLoss[$classKey] += -$gain;
                } elseif ($gain > 0 && ! $exempt) {
                    $taxable = max(0.0, $gain - $carryLoss[$classKey]);
                    $carryLoss[$classKey] = max(0.0, $carryLoss[$classKey] - $gain);
                    $tax = round($taxable * $config['rate'], 2);
                }

                $row[$classKey] = [
                    'vendas' => $sales,
                    'ganho' => $gain,
                    'isento' => $exempt,
                    'darf' => $tax,
                ];
                $row['darf'] += $tax;
            }

            $row['darf'] = round($row['darf'], 2);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function bensEDireitos(Collection $assets, int $year): array
    {
        $end = "{$year}-12-31";
        $previousEnd = ($year - 1).'-12-31';
        $rows = [];

        foreach ($assets as $asset) {
            $quantity = $asset->positionQuantity($end);
            $previousCost = max(0.0, $asset->purchaseValue($previousEnd));
            $cost = max(0.0, $asset->purchaseValue($end));

            // Sem custo em nenhum dos dois 31/12 não há o que declarar (ex:
            // direitos de subscrição recebidos de graça e posições zeradas).
            if ($previousCost <= 0.005 && $cost <= 0.005) {
                continue;
            }

            $rows[] = [
                'ticker' => $asset->ticker_or_code,
                'nome' => $asset->name,
                'tipo' => $asset->type,
                'grupo_codigo' => $this->grupoCodigoSugerido($asset),
                'quantidade' => round($quantity, 6),
                'custo_anterior' => round($previousCost, 2),
                'custo_atual' => round($cost, 2),
                'discriminacao' => $this->discriminacao($asset, $quantity, $cost),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function proventosDoAno(Collection $assets, int $year): array
    {
        $rows = [];
        $converter = app(CurrencyConverter::class);

        foreach ($assets as $asset) {
            $inYear = $asset->transactions->filter(
                fn (Transaction $t): bool => substr((string) $t->getRawOriginal('transaction_date'), 0, 4) === (string) $year
            );

            $signed = fn (Collection $group): float => (float) $group->sum(
                fn (Transaction $t) => ($t->isCredit() ? 1 : -1) * $converter->toBrl(
                    (float) $t->total_amount,
                    $asset->currency,
                    substr((string) $t->getRawOriginal('transaction_date'), 0, 10),
                )
            );

            $isentos = $signed($inYear->whereIn('type', self::EXEMPT_TYPES));
            $exclusivos = $signed($inYear->whereIn('type', self::EXCLUSIVE_TYPES));
            $outros = $signed($inYear->whereIn('type', ['AMORTIZATION', 'FRACTION_AUCTION']));

            if (abs($isentos) < 0.005 && abs($exclusivos) < 0.005 && abs($outros) < 0.005) {
                continue;
            }

            $rows[] = [
                'ticker' => $asset->ticker_or_code,
                'nome' => $asset->name,
                'isentos' => round($isentos, 2),
                'exclusivos' => round($exclusivos, 2),
                'outros' => round($outros, 2),
            ];
        }

        usort($rows, fn (array $a, array $b): int => ($b['isentos'] + $b['exclusivos']) <=> ($a['isentos'] + $a['exclusivos']));

        return $rows;
    }

    /**
     * Total vendido por mês, separado por classe — em ações, vendas até
     * R$ 20 mil no mês são isentas de ganho de capital (FIIs e opções não
     * têm isenção).
     *
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function vendasPorMes(Collection $assets, int $year): array
    {
        $months = [];
        $converter = app(CurrencyConverter::class);

        foreach ($assets as $asset) {
            $sells = $asset->transactions->filter(
                fn (Transaction $t): bool => $t->type === 'SELL'
                    && (float) $t->total_amount > 0
                    && substr((string) $t->getRawOriginal('transaction_date'), 0, 4) === (string) $year
            );

            foreach ($sells as $sell) {
                $sellDate = substr((string) $sell->getRawOriginal('transaction_date'), 0, 10);
                $month = (int) substr($sellDate, 5, 2);
                $bucket = match ($asset->type) {
                    'STOCK' => 'acoes',
                    'FII' => 'fiis',
                    'OPTION' => 'opcoes',
                    default => 'outros',
                };

                $months[$month][$bucket] = ($months[$month][$bucket] ?? 0.0)
                    + $converter->toBrl((float) $sell->total_amount, $asset->currency, $sellDate);
            }
        }

        ksort($months);

        $rows = [];

        foreach ($months as $month => $totals) {
            $acoes = round($totals['acoes'] ?? 0.0, 2);

            $rows[] = [
                'mes' => $month,
                'acoes' => $acoes,
                'fiis' => round($totals['fiis'] ?? 0.0, 2),
                'opcoes' => round($totals['opcoes'] ?? 0.0, 2),
                'outros' => round($totals['outros'] ?? 0.0, 2),
                'acoes_acima_isencao' => $acoes > 20000,
            ];
        }

        return $rows;
    }

    private function grupoCodigoSugerido(Asset $asset): string
    {
        return match ($asset->type) {
            'STOCK' => '03 / 01 — Ações',
            'FII' => '07 / 03 — Fundos imobiliários',
            'OPTION' => '04 / 99 — Outras aplicações e investimentos',
            'FIXED_INCOME' => str_starts_with((string) $asset->ticker_or_code, 'CDB')
                ? '04 / 02 — CDB/RDB'
                : '04 / 03 — Demais títulos',
            'REAL_ESTATE' => '01 — Bens imóveis (código conforme o tipo)',
            'VEHICLE' => '02 / 01 — Veículos',
            'MACHINERY' => '02 / 99 — Máquinas e equipamentos',
            'COMMODITY' => '04 / 04 — Ouro/ativo financeiro',
            'SOFTWARE' => '99 / 07 — Software / propriedade intelectual',
            default => '99 / 99 — Outros bens',
        };
    }

    private function discriminacao(Asset $asset, float $quantity, float $cost): string
    {
        $qty = rtrim(rtrim(number_format($quantity, 6, ',', '.'), '0'), ',');
        $money = 'R$ '.number_format($cost, 2, ',', '.');
        $institution = $asset->currentInstitution();
        $custodia = $institution ? " Custódia: {$institution}." : '';
        $empresa = $asset->company?->name ? " Registrado em: {$asset->company->name}." : '';

        return match ($asset->type) {
            'STOCK' => "{$qty} ações de {$asset->name} ({$asset->ticker_or_code}), custo total de aquisição {$money}.{$custodia}",
            'FII' => "{$qty} cotas do fundo imobiliário {$asset->name} ({$asset->ticker_or_code}), custo total de aquisição {$money}.{$custodia}",
            'OPTION' => "{$qty} opções {$asset->name} ({$asset->ticker_or_code}), prêmio/custo total {$money}.{$custodia}",
            'FIXED_INCOME' => "Aplicação em {$asset->name}, custo de aquisição {$money}.{$custodia}",
            'VEHICLE', 'MACHINERY', 'REAL_ESTATE', 'COMMODITY', 'COLLECTIBLE', 'SOFTWARE' => ucfirst(mb_strtolower(Asset::TYPE_LABELS[$asset->type])).": {$asset->name}, custo total de aquisição (com benfeitorias e despesas) {$money}.{$empresa}",
            default => "{$qty} unidades de {$asset->name}, custo total de aquisição {$money}.{$custodia}",
        };
    }
}
