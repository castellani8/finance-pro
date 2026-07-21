<?php

namespace App\Services;

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

        $bens = $this->bensEDireitos($assets, $year);
        $proventos = $this->proventosDoAno($assets, $year);
        $vendas = $this->vendasPorMes($assets, $year);

        return [
            'year' => $year,
            'bens' => $bens,
            'proventos' => $proventos,
            'vendas' => $vendas,
            'totais' => [
                'custo_anterior' => round(array_sum(array_column($bens, 'custo_anterior')), 2),
                'custo_atual' => round(array_sum(array_column($bens, 'custo_atual')), 2),
                'isentos' => round(array_sum(array_column($proventos, 'isentos')), 2),
                'exclusivos' => round(array_sum(array_column($proventos, 'exclusivos')), 2),
                'outros' => round(array_sum(array_column($proventos, 'outros')), 2),
            ],
        ];
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

        foreach ($assets as $asset) {
            $inYear = $asset->transactions->filter(
                fn (Transaction $t): bool => substr((string) $t->getRawOriginal('transaction_date'), 0, 4) === (string) $year
            );

            $signed = fn (Collection $group): float => (float) $group->sum(
                fn (Transaction $t) => ($t->isCredit() ? 1 : -1) * (float) $t->total_amount
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
     * R$ 20 mil no mês são isentas de ganho de capital (FIIs não têm isenção).
     *
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function vendasPorMes(Collection $assets, int $year): array
    {
        $months = [];

        foreach ($assets as $asset) {
            $sells = $asset->transactions->filter(
                fn (Transaction $t): bool => $t->type === 'SELL'
                    && (float) $t->total_amount > 0
                    && substr((string) $t->getRawOriginal('transaction_date'), 0, 4) === (string) $year
            );

            foreach ($sells as $sell) {
                $month = (int) substr((string) $sell->getRawOriginal('transaction_date'), 5, 2);
                $bucket = match ($asset->type) {
                    'STOCK' => 'acoes',
                    'FII' => 'fiis',
                    default => 'outros',
                };

                $months[$month][$bucket] = ($months[$month][$bucket] ?? 0.0) + (float) $sell->total_amount;
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
            'FIXED_INCOME' => "Aplicação em {$asset->name}, custo de aquisição {$money}.{$custodia}",
            'VEHICLE', 'MACHINERY', 'REAL_ESTATE', 'COMMODITY', 'COLLECTIBLE', 'SOFTWARE' => ucfirst(mb_strtolower(Asset::TYPE_LABELS[$asset->type])).": {$asset->name}, custo total de aquisição (com benfeitorias e despesas) {$money}.{$empresa}",
            default => "{$qty} unidades de {$asset->name}, custo total de aquisição {$money}.{$custodia}",
        };
    }
}
