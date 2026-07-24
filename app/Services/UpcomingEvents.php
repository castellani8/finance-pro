<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\RecurringTransaction;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Agenda financeira do mês: junta o que é contratual (recorrências e
 * vencimentos de renda fixa) com o que dá para estimar do histórico
 * (proventos de pagadores regulares, tipo FIIs). Estimativas são sempre
 * marcadas como tal — a Milia não inventa certeza que não tem.
 */
class UpcomingEvents
{
    /** Meses de histórico analisados para estimar proventos recorrentes. */
    private const LOOKBACK_MONTHS = 6;

    /** Mínimo de meses com pagamento no período para considerar o ativo um pagador regular. */
    private const MIN_PAYING_MONTHS = 3;

    /**
     * @return array{
     *     events: array<int, array{date: string, day: int, kind: string, label: string, detail: ?string, amount: ?float, estimated: bool}>,
     *     totals: array{a_receber: float, a_pagar: float, vencendo: float}
     * }
     */
    public function month(Tenant $tenant, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $events = [
            ...$this->recurrences($tenant, $start, $end),
            ...$this->fixedIncomeMaturities($tenant, $start, $end),
            ...$this->estimatedDividends($tenant, $start, $end),
        ];

        usort($events, fn (array $a, array $b): int => [$a['date'], $a['label']] <=> [$b['date'], $b['label']]);

        $sum = fn (string ...$kinds): float => round(array_sum(array_map(
            fn (array $e): float => (float) ($e['amount'] ?? 0),
            array_filter($events, fn (array $e): bool => in_array($e['kind'], $kinds, true)),
        )), 2);

        return [
            'events' => $events,
            'totals' => [
                'a_receber' => $sum('receita', 'provento'),
                'a_pagar' => $sum('despesa'),
                'vencendo' => $sum('vencimento'),
            ],
        ];
    }

    /** Recorrências ativas com vencimento dentro do mês. */
    private function recurrences(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        return RecurringTransaction::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('active', true)
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start->toDateString()))
            ->get()
            ->map(function (RecurringTransaction $contract) use ($start): array {
                // Dia 31 em mês curto cai no último dia (mesma regra do gerador).
                $day = min((int) $contract->day_of_month, $start->daysInMonth);
                $date = $start->copy()->day($day);

                return [
                    'date' => $date->toDateString(),
                    'day' => $day,
                    'kind' => $contract->type === 'EXPENSE' ? 'despesa' : 'receita',
                    'label' => $contract->description,
                    'detail' => $contract->type === 'EXPENSE' ? 'Despesa recorrente' : 'Receita recorrente',
                    'amount' => (float) $contract->amount,
                    'estimated' => false,
                ];
            })
            ->filter(fn (array $event): bool => $event['date'] >= $start->toDateString())
            ->values()
            ->all();
    }

    /** Títulos de renda fixa com posição vencendo dentro do mês. */
    private function fixedIncomeMaturities(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        $assets = Asset::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', 'FIXED_INCOME')
            ->wherePositionPositive()
            ->with('transactions')
            ->get()
            ->filter(function (Asset $asset) use ($start, $end): bool {
                $due = $asset->metadata['due_date'] ?? null;

                return is_string($due) && $due >= $start->toDateString() && $due <= $end->toDateString();
            });

        Asset::primeMarketData($assets);

        return $assets->map(fn (Asset $asset): array => [
            'date' => $asset->metadata['due_date'],
            'day' => (int) substr($asset->metadata['due_date'], 8, 2),
            'kind' => 'vencimento',
            'label' => $asset->name,
            'detail' => 'Vencimento de renda fixa — registre o resgate quando o valor cair',
            'amount' => round($asset->currentValue(), 2),
            'estimated' => false,
        ])->values()->all();
    }

    /**
     * Proventos estimados: ativos que pagaram em pelo menos 3 dos últimos 6
     * meses viram uma previsão (mediana dos valores, dia típico de pagamento).
     */
    private function estimatedDividends(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        $lookbackStart = $start->copy()->subMonthsNoOverflow(self::LOOKBACK_MONTHS)->startOfMonth();

        $rows = $tenant->transactions()
            ->whereIn('type', Asset::CASH_INCOME_TYPES)
            ->whereNotNull('asset_id')
            ->whereDate('transaction_date', '>=', $lookbackStart->toDateString())
            ->whereDate('transaction_date', '<', $start->toDateString())
            ->with('asset')
            ->get();

        $events = [];

        foreach ($rows->groupBy('asset_id') as $transactions) {
            $asset = $transactions->first()->asset;

            if ($asset === null) {
                continue;
            }

            $byMonth = $transactions->groupBy(
                fn ($t): string => substr((string) $t->getRawOriginal('transaction_date'), 0, 7)
            );

            if ($byMonth->count() < self::MIN_PAYING_MONTHS) {
                continue;
            }

            // Mediana dos totais mensais (sinal do fluxo trata estornos).
            $monthlyTotals = $byMonth
                ->map(fn ($group): float => (float) $group->sum(
                    fn ($t): float => $t->flowDirection()->sign() * (float) $t->total_amount
                ))
                ->filter(fn (float $v): bool => $v > 0)
                ->sort()
                ->values();

            if ($monthlyTotals->isEmpty()) {
                continue;
            }

            $estimate = $monthlyTotals[intdiv($monthlyTotals->count() - 1, 2)];

            // Dia típico de pagamento (mediana), limitado ao tamanho do mês.
            $days = $transactions
                ->map(fn ($t): int => (int) substr((string) $t->getRawOriginal('transaction_date'), 8, 2))
                ->sort()
                ->values();
            $day = min($days[intdiv($days->count() - 1, 2)], $start->daysInMonth);

            $events[] = [
                'date' => $start->copy()->day($day)->toDateString(),
                'day' => $day,
                'kind' => 'provento',
                'label' => $asset->name,
                'detail' => 'Provento estimado pela média dos últimos meses',
                'amount' => round($estimate, 2),
                'estimated' => true,
            ];
        }

        return $events;
    }
}
