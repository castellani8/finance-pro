<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Company;
use App\Models\PortfolioSnapshot;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Exclusão de conta conforme a LGPD (art. 18, VI): apaga definitivamente o
 * usuário e todos os dados financeiros das carteiras em que ele é o único
 * membro. Carteiras compartilhadas com outros usuários são apenas desvinculadas.
 */
class AccountDeletion
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            foreach ($user->tenants()->get() as $tenant) {
                $isOnlyMember = $tenant->users()->count() === 1;

                if (! $isOnlyMember) {
                    $tenant->users()->detach($user);

                    continue;
                }

                Transaction::where('tenant_id', $tenant->getKey())->delete();
                Asset::where('tenant_id', $tenant->getKey())->delete();
                PortfolioSnapshot::where('tenant_id', $tenant->getKey())->delete();
                $tenant->users()->detach();
                $tenant->delete();
            }

            $user->delete();
        });
    }

    /** @return array<string, mixed> */
    private static function transactionPayload(Transaction $t): array
    {
        return [
            'data' => $t->transaction_date?->toDateString(),
            'tipo' => $t->type,
            'movimentacao' => $t->movement,
            'sentido' => $t->direction,
            'quantidade' => (float) $t->quantity,
            'preco_unitario' => $t->unit_price !== null ? (float) $t->unit_price : null,
            'valor_total' => (float) $t->total_amount,
            'instituicao' => $t->institution,
            'origem' => $t->source,
        ];
    }

    /**
     * Todos os dados do usuário em formato de portabilidade (LGPD art. 18, V).
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'exportado_em' => now()->toIso8601String(),
            'usuario' => [
                'nome' => $user->name,
                'email' => $user->email,
                'criado_em' => $user->created_at?->toIso8601String(),
            ],
            'carteiras' => $user->tenants()->get()->map(fn ($tenant): array => [
                'nome' => $tenant->name,
                'ativos' => Asset::with('transactions')
                    ->where('tenant_id', $tenant->getKey())
                    ->get()
                    ->map(fn (Asset $asset): array => [
                        'nome' => $asset->name,
                        'tipo' => $asset->type,
                        'ticker_ou_codigo' => $asset->ticker_or_code,
                        'moeda' => $asset->currency,
                        'metadata' => $asset->metadata,
                        'movimentacoes' => $asset->transactions->map(fn (Transaction $t): array => self::transactionPayload($t))->all(),
                    ])->all(),
                'lancamentos_avulsos' => Transaction::with('company')
                    ->where('tenant_id', $tenant->getKey())
                    ->whereNull('asset_id')
                    ->get()
                    ->map(fn (Transaction $t): array => [
                        ...self::transactionPayload($t),
                        'empresa' => $t->company?->name,
                        'categoria' => $t->category,
                    ])->all(),
                'empresas' => Company::where('tenant_id', $tenant->getKey())
                    ->get(['name', 'document', 'email', 'phone', 'address', 'city', 'state', 'zip'])
                    ->toArray(),
                'contas' => Account::with('transactions')
                    ->where('tenant_id', $tenant->getKey())
                    ->get()
                    ->map(fn (Account $account): array => [
                        'nome' => $account->name,
                        'tipo' => $account->kind,
                        'saldo_inicial' => (float) $account->opening_balance,
                        'saldo_atual' => $account->balance(),
                    ])->all(),
                'recorrencias' => RecurringTransaction::where('tenant_id', $tenant->getKey())
                    ->get()
                    ->map(fn ($r): array => [
                        'descricao' => $r->description,
                        'tipo' => $r->type,
                        'valor' => (float) $r->amount,
                        'dia_do_mes' => $r->day_of_month,
                        'inicio' => $r->starts_on?->toDateString(),
                        'fim' => $r->ends_on?->toDateString(),
                        'ativa' => (bool) $r->active,
                    ])->all(),
            ])->all(),
        ];
    }
}
