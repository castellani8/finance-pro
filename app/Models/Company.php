<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'document', 'phone', 'email', 'address', 'city', 'state', 'zip'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Receitas dos últimos 12 meses: lançamentos avulsos apontando para a
     * empresa MAIS as rendas dos ativos associados a ela.
     */
    /** Memoização por request: a tabela pede receita/despesa/resultado da mesma linha. */
    private ?float $memoizedIncome = null;

    private ?float $memoizedExpenses = null;

    public function incomeLastTwelveMonths(): float
    {
        // Crédito soma, débito (estorno) subtrai.
        return $this->memoizedIncome ??= $this->sumTransactions(Asset::CASH_INCOME_TYPES, creditSign: 1);
    }

    /** Despesas dos últimos 12 meses (diretas + dos ativos da empresa). */
    public function expensesLastTwelveMonths(): float
    {
        // Débito soma (é a despesa), crédito (reembolso) subtrai.
        return $this->memoizedExpenses ??= $this->sumTransactions(['EXPENSE'], creditSign: -1);
    }

    /** Resultado (receitas − despesas) dos últimos 12 meses. */
    public function netResultLastTwelveMonths(): float
    {
        return $this->incomeLastTwelveMonths() - $this->expensesLastTwelveMonths();
    }

    /**
     * @param  array<int, string>  $types
     */
    private function sumTransactions(array $types, int $creditSign): float
    {
        $cutoff = now()->subMonthsNoOverflow(12)->toDateString();

        return (float) Transaction::query()
            ->whereIn('type', $types)
            ->where('transaction_date', '>=', $cutoff)
            ->where(fn ($query) => $query
                ->where('company_id', $this->getKey())
                ->orWhereIn('asset_id', fn ($sub) => $sub
                    ->select('id')->from('assets')->where('company_id', $this->getKey())))
            ->get(['total_amount', 'direction', 'type'])
            ->sum(fn (Transaction $t) => ($t->isCredit() ? $creditSign : -$creditSign) * (float) $t->total_amount);
    }
}
