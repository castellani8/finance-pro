<?php

namespace App\Ai\Tools;

use App\Models\Company;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Empresas do usuário — o caminho para descobrir o nome exato antes de
 * filtrar faturamento/fluxo por empresa.
 */
class ConsultarEmpresas extends MilhaTool
{
    public function description(): string
    {
        return 'Lista as empresas cadastradas pelo usuário (nome e quantidade de ativos '
            .'e lançamentos vinculados). Use antes de consultar faturamento ou fluxo de '
            .'caixa de uma empresa específica.';
    }

    public function handle(Request $request): string
    {
        $companies = Company::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->withCount('assets')
            ->orderBy('name')
            ->get();

        if ($companies->isEmpty()) {
            return $this->json(['empresas' => [], 'observacao' => 'Nenhuma empresa cadastrada.']);
        }

        return $this->json([
            'empresas' => $companies->map(fn (Company $c): array => [
                'nome' => $c->name,
                'ativos_vinculados' => $c->assets_count,
                'receitas_ultimos_12_meses' => $this->money($c->incomeLastTwelveMonths()),
                'despesas_ultimos_12_meses' => $this->money($c->expensesLastTwelveMonths()),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
