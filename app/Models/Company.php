<?php

namespace App\Models;

use App\Services\CurrencyConverter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Company extends Model
{
    use LogsActivity;

    /** Auditoria: registra criações/edições/exclusões dos campos preenchíveis. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

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
        $converter = app(CurrencyConverter::class);

        return (float) Transaction::query()
            ->whereIn('transactions.type', $types)
            ->where('transactions.transaction_date', '>=', $cutoff)
            ->where(fn ($query) => $query
                ->where('transactions.company_id', $this->getKey())
                ->orWhereIn('asset_id', fn ($sub) => $sub
                    ->select('id')->from('assets')->where('company_id', $this->getKey())))
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->get([
                'transactions.transaction_date', 'transactions.total_amount',
                'transactions.direction', 'transactions.type',
                'assets.currency as asset_currency', 'accounts.currency as account_currency',
            ])
            ->sum(fn (Transaction $t) => ($t->isCredit() ? $creditSign : -$creditSign) * $converter->toBrl(
                (float) $t->total_amount,
                $t->asset_currency ?? $t->account_currency ?? 'BRL',
                $t->transaction_date->toDateString(),
            ));
    }
}
