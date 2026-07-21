<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Support\PortfolioCache;
use Illuminate\Support\Carbon;

/**
 * Materializa os contratos recorrentes em lançamentos reais: para cada
 * contrato ativo, cria uma Transaction em cada vencimento entre a última
 * geração e hoje (com catch-up de meses perdidos se o scheduler ficou fora).
 */
class RecurringTransactionGenerator
{
    /** @return int Quantidade de lançamentos criados. */
    public function generateDue(?Tenant $tenant = null, ?Carbon $today = null): int
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $created = 0;
        $touchedTenants = [];

        $contracts = RecurringTransaction::query()
            ->where('active', true)
            ->when($tenant, fn ($query) => $query->where('tenant_id', $tenant->getKey()))
            ->get();

        foreach ($contracts as $contract) {
            $generated = $this->generateForContract($contract, $today);

            if ($generated > 0) {
                $created += $generated;
                $touchedTenants[$contract->tenant_id] = true;
            }
        }

        foreach (array_keys($touchedTenants) as $tenantId) {
            PortfolioCache::bump($tenantId);
        }

        return $created;
    }

    private function generateForContract(RecurringTransaction $contract, Carbon $today): int
    {
        $created = 0;
        $dueDate = $this->firstDueDate($contract);

        while ($dueDate !== null && $dueDate->lessThanOrEqualTo($today)) {
            if ($contract->ends_on !== null && $dueDate->greaterThan($contract->ends_on)) {
                break;
            }

            Transaction::create([
                'tenant_id' => $contract->tenant_id,
                'asset_id' => $contract->asset_id,
                'company_id' => $contract->company_id,
                'recurring_transaction_id' => $contract->getKey(),
                'type' => $contract->type,
                'transaction_date' => $dueDate->toDateString(),
                'quantity' => 1,
                'total_amount' => (float) $contract->amount,
                'direction' => $contract->type === 'EXPENSE' ? 'Debito' : 'Credito',
                'movement' => $contract->description,
                'category' => $contract->category,
                'source' => 'recurring',
            ]);

            $contract->last_generated_on = $dueDate->toDateString();
            $created++;

            $dueDate = $this->dueDateForMonth($contract->day_of_month, $dueDate->copy()->addMonthNoOverflow());
        }

        if ($created > 0) {
            $contract->save();
        }

        return $created;
    }

    /** Primeiro vencimento ainda não gerado. */
    private function firstDueDate(RecurringTransaction $contract): ?Carbon
    {
        $start = $contract->starts_on->copy()->startOfDay();

        if ($contract->last_generated_on === null) {
            $candidate = $this->dueDateForMonth($contract->day_of_month, $start);

            // Se o dia do mês inicial já passou quando o contrato começa,
            // o primeiro vencimento cai no mês seguinte.
            return $candidate->greaterThanOrEqualTo($start)
                ? $candidate
                : $this->dueDateForMonth($contract->day_of_month, $start->addMonthNoOverflow());
        }

        return $this->dueDateForMonth(
            $contract->day_of_month,
            $contract->last_generated_on->copy()->startOfDay()->addMonthNoOverflow(),
        );
    }

    /** Vencimento dentro do mês de $reference (dia 31 vira o último dia do mês). */
    private function dueDateForMonth(int $dayOfMonth, Carbon $reference): Carbon
    {
        $day = min($dayOfMonth, $reference->daysInMonth);

        return $reference->copy()->setDay($day)->startOfDay();
    }
}
