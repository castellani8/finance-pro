<?php

namespace App\Ai\Tools;

use App\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Contas do usuário com saldos atuais. Também é o caminho para descobrir o
 * account_id ao criar um lançamento vinculado a conta.
 */
class ConsultarContas extends MilhaTool
{
    public function description(): string
    {
        return 'Lista as contas do usuário (banco, corretora, caixa) com saldo atual '
            .'de cada uma e o total em reais. Use esta tool para descobrir o id de uma '
            .'conta antes de criar um lançamento vinculado a ela.';
    }

    public function handle(Request $request): string
    {
        $accounts = Account::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->with('transactions')
            ->orderBy('name')
            ->get();

        return $this->json([
            'total_em_reais' => $this->money($accounts->sum(fn (Account $a): float => $a->balanceInBrlAt())),
            'contas' => $accounts->map(fn (Account $a): array => [
                'id' => $a->getKey(),
                'nome' => $a->name,
                'tipo' => $a->kind,
                'moeda' => $a->currency ?? 'BRL',
                'saldo' => number_format($a->balance(), 2, ',', '.'),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
