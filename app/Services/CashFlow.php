<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Support\CompanyFilter;

/**
 * Fluxo de caixa mensal do tenant: TODAS as receitas (proventos, aluguéis,
 * rendas avulsas) contra TODAS as despesas (de ativos e avulsas), com o
 * resultado do período — opcionalmente filtrado por empresa (lançamentos
 * diretos nela + dos ativos associados a ela).
 */
class CashFlow
{
    /**
     * @return array{labels: array<int, string>, income: array<int, float>, expenses: array<int, float>, result: array<int, float>}
     */
    public function monthly(Tenant $tenant, int $months = 12, int|string|null $companyId = null): array
    {
        $start = now()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $rows = Transaction::query()
            ->where('transactions.tenant_id', $tenant->getKey())
            ->whereIn('transactions.type', [...Asset::CASH_INCOME_TYPES, 'EXPENSE'])
            ->where('transactions.transaction_date', '>=', $start->toDateString())
            ->when(is_int($companyId), fn ($query) => $query->where(fn ($q) => $q
                ->where('transactions.company_id', $companyId)
                ->orWhereIn('asset_id', fn ($sub) => $sub
                    ->select('id')->from('assets')->where('company_id', $companyId))))
            // "Sem empresa": lançamento sem empresa E (avulso ou de ativo sem empresa).
            ->when($companyId === CompanyFilter::NONE, fn ($query) => $query
                ->whereNull('transactions.company_id')
                ->where(fn ($q) => $q
                    ->whereNull('asset_id')
                    ->orWhereIn('asset_id', fn ($sub) => $sub
                        ->select('id')->from('assets')->whereNull('company_id'))))
            // Moeda do lançamento: a do ativo, senão a da conta, senão BRL.
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->get([
                'transactions.transaction_date', 'transactions.total_amount',
                'transactions.direction', 'transactions.type',
                'assets.currency as asset_currency', 'accounts.currency as account_currency',
            ]);

        $converter = app(CurrencyConverter::class);
        $incomeByMonth = [];
        $expensesByMonth = [];

        foreach ($rows as $row) {
            $month = $row->transaction_date->format('Y-m');
            $amount = $converter->toBrl(
                (float) $row->total_amount,
                $row->asset_currency ?? $row->account_currency ?? 'BRL',
                $row->transaction_date->toDateString(),
            );

            if ($row->type === 'EXPENSE') {
                // Débito é a despesa; crédito é reembolso.
                $expensesByMonth[$month] = ($expensesByMonth[$month] ?? 0.0) + ($row->isCredit() ? -$amount : $amount);
            } else {
                // Crédito é a receita; débito é estorno.
                $incomeByMonth[$month] = ($incomeByMonth[$month] ?? 0.0) + ($row->isCredit() ? $amount : -$amount);
            }
        }

        $labels = [];
        $income = [];
        $expenses = [];
        $result = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo(now())) {
            $key = $cursor->format('Y-m');
            $monthIncome = round($incomeByMonth[$key] ?? 0.0, 2);
            $monthExpenses = round($expensesByMonth[$key] ?? 0.0, 2);

            $labels[] = $cursor->locale('pt_BR')->translatedFormat('M/y');
            $income[] = $monthIncome;
            $expenses[] = $monthExpenses;
            $result[] = round($monthIncome - $monthExpenses, 2);

            $cursor = $cursor->addMonthNoOverflow();
        }

        return ['labels' => $labels, 'income' => $income, 'expenses' => $expenses, 'result' => $result];
    }
}
