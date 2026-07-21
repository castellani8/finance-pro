<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;

/**
 * Renda passiva mensal consolidada — tudo que os ATIVOS pagam sem você
 * trabalhar: aluguéis/rendas de bens físicos, proventos da bolsa e juros de
 * renda fixa, convertidos para BRL pela cotação da data.
 */
class PassiveIncome
{
    /**
     * @return array{labels: array<int, string>, alugueis: array<int, float>, proventos: array<int, float>, juros: array<int, float>, total: array<int, float>}
     */
    public function monthly(Tenant $tenant, int $months = 12): array
    {
        $start = now()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $converter = app(CurrencyConverter::class);

        $rows = Transaction::query()
            ->where('transactions.tenant_id', $tenant->getKey())
            ->whereIn('transactions.type', Asset::CASH_INCOME_TYPES)
            ->whereNotNull('asset_id')
            ->where('transactions.transaction_date', '>=', $start->toDateString())
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->get([
                'transactions.transaction_date', 'transactions.total_amount',
                'transactions.direction', 'transactions.type',
                'assets.type as asset_type', 'assets.currency as asset_currency',
            ]);

        $byMonth = [];

        foreach ($rows as $row) {
            $month = $row->transaction_date->format('Y-m');
            $amount = $row->flowDirection()->sign() * $converter->toBrl(
                (float) $row->total_amount,
                $row->asset_currency ?? 'BRL',
                $row->transaction_date->toDateString(),
            );

            $bucket = match (true) {
                in_array($row->asset_type, Asset::PHYSICAL_TYPES, true) => 'alugueis',
                $row->type === 'INTEREST' => 'juros',
                default => 'proventos',
            };

            $byMonth[$month][$bucket] = ($byMonth[$month][$bucket] ?? 0.0) + $amount;
        }

        $labels = [];
        $alugueis = [];
        $proventos = [];
        $juros = [];
        $total = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo(now())) {
            $key = $cursor->format('Y-m');

            $labels[] = $cursor->locale('pt_BR')->translatedFormat('M/y');
            $alugueis[] = round($byMonth[$key]['alugueis'] ?? 0.0, 2);
            $proventos[] = round($byMonth[$key]['proventos'] ?? 0.0, 2);
            $juros[] = round($byMonth[$key]['juros'] ?? 0.0, 2);
            $total[] = round(
                ($byMonth[$key]['alugueis'] ?? 0.0)
                + ($byMonth[$key]['proventos'] ?? 0.0)
                + ($byMonth[$key]['juros'] ?? 0.0),
                2,
            );

            $cursor = $cursor->addMonthNoOverflow();
        }

        return [
            'labels' => $labels,
            'alugueis' => $alugueis,
            'proventos' => $proventos,
            'juros' => $juros,
            'total' => $total,
        ];
    }
}
