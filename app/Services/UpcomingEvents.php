<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\RecurringTransaction;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Agenda financeira do mês: só o que é contratual — recorrências (aluguéis,
 * assinaturas) e vencimentos de renda fixa. Proventos ficam de fora de
 * propósito: sem fonte oficial de anúncios, seria chute — e a Milia não
 * inventa certeza que não tem.
 */
class UpcomingEvents
{
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
        ];

        usort($events, fn (array $a, array $b): int => [$a['date'], $a['label']] <=> [$b['date'], $b['label']]);

        $sum = fn (string ...$kinds): float => round(array_sum(array_map(
            fn (array $e): float => (float) ($e['amount'] ?? 0),
            array_filter($events, fn (array $e): bool => in_array($e['kind'], $kinds, true)),
        )), 2);

        return [
            'events' => $events,
            'totals' => [
                'a_receber' => $sum('receita'),
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
}
