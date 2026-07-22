<?php

namespace App\Ai\Tools;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Lançamentos avulsos (receitas/despesas sem ativo), com filtros básicos.
 */
class ConsultarLancamentos extends MilhaTool
{
    public function description(): string
    {
        return 'Lista os lançamentos avulsos do usuário (receitas e despesas do dia a dia, '
            .'sem vínculo com ativo), do mais recente para o mais antigo, com filtros '
            .'opcionais por período, tipo (INCOME/EXPENSE) e categoria.';
    }

    public function handle(Request $request): string
    {
        $limit = min(50, max(1, $request->integer('limite', 20)));

        $rows = Transaction::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->whereNull('asset_id')
            ->when($request->filled('data_inicio'), fn ($q) => $q
                ->where('transaction_date', '>=', $request->string('data_inicio')->toString()))
            ->when($request->filled('data_fim'), fn ($q) => $q
                ->where('transaction_date', '<=', $request->string('data_fim')->toString()))
            ->when($request->filled('tipo'), fn ($q) => $q->where('type', $request->string('tipo')->toString()))
            ->when($request->filled('categoria'), fn ($q) => $q->where('category', $request->string('categoria')->toString()))
            ->with(['account', 'company'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return $this->json([
            'lancamentos' => $rows->map(fn (Transaction $t): array => array_filter([
                'data' => $t->transaction_date->toDateString(),
                'tipo' => $t->type === 'EXPENSE' ? 'Despesa' : 'Receita',
                'descricao' => $t->movement,
                'categoria' => $t->category,
                'valor' => $this->money((float) $t->total_amount),
                'conta' => $t->account?->name,
                'empresa' => $t->company?->name,
            ], fn ($v) => $v !== null))->all(),
            'ha_mais_resultados' => $hasMore,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'data_inicio' => $schema->string()->description('Filtro opcional: início do período, YYYY-MM-DD.'),
            'data_fim' => $schema->string()->description('Filtro opcional: fim do período, YYYY-MM-DD.'),
            'tipo' => $schema->string()->enum(['INCOME', 'EXPENSE'])
                ->description('Filtro opcional: INCOME (receita) ou EXPENSE (despesa).'),
            'categoria' => $schema->string()->description('Filtro opcional por categoria exata.'),
            'limite' => $schema->integer()->description('Máximo de lançamentos (1 a 50). Padrão: 20.'),
        ];
    }
}
