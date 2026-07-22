<?php

namespace App\Ai\Tools;

use App\Models\Company;
use App\Services\CashFlow;
use App\Support\CompanyFilter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Fluxo de caixa mensal: receitas (faturamento), despesas e resultado —
 * geral, de uma empresa específica ou só do que não tem empresa.
 */
class ConsultarFluxoDeCaixa extends MilhaTool
{
    public function description(): string
    {
        return 'Fluxo de caixa mensal: receitas (faturamento), despesas e resultado, mês a '
            .'mês, em reais. Aceita filtro opcional por empresa (nome cadastrado, veja '
            .'ConsultarEmpresas) ou "sem empresa" para a vida pessoal. Para saber o '
            .'faturamento de um mês específico, peça meses suficientes e leia o mês na resposta.';
    }

    public function handle(Request $request): string
    {
        $months = min(60, max(1, $request->integer('meses', 12)));
        $companyId = null;

        if ($request->filled('empresa')) {
            $nome = trim($request->string('empresa')->toString());

            if (mb_strtolower($nome) === 'sem empresa') {
                $companyId = CompanyFilter::NONE;
            } else {
                // lower() dos dois lados: LIKE é case-sensitive no PostgreSQL.
                $matches = Company::query()
                    ->where('tenant_id', $this->tenant->getKey())
                    ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($nome).'%'])
                    ->get();

                if ($matches->isEmpty()) {
                    return $this->json([
                        'erro' => "Nenhuma empresa encontrada com o nome \"{$nome}\".",
                        'empresas_cadastradas' => Company::query()
                            ->where('tenant_id', $this->tenant->getKey())
                            ->orderBy('name')
                            ->pluck('name')
                            ->all(),
                    ]);
                }

                if ($matches->count() > 1) {
                    return $this->json([
                        'erro' => "Mais de uma empresa combina com \"{$nome}\" — peça para o usuário especificar.",
                        'candidatas' => $matches->pluck('name')->all(),
                    ]);
                }

                $companyId = (int) $matches->first()->getKey();
            }
        }

        $data = app(CashFlow::class)->monthly($this->tenant, $months, $companyId);

        // Chaves YYYY-MM: os labels do serviço ("mai/26") são para gráfico;
        // para o modelo, o formato ISO elimina ambiguidade.
        $cursor = now()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $porMes = [];

        foreach ($data['labels'] as $i => $label) {
            $porMes[$cursor->format('Y-m')] = [
                'receitas' => $this->money($data['income'][$i]),
                'despesas' => $this->money($data['expenses'][$i]),
                'resultado' => $this->money($data['result'][$i]),
            ];

            $cursor = $cursor->addMonthNoOverflow();
        }

        return $this->json([
            'meses_considerados' => $months,
            'filtro_empresa' => $request->filled('empresa') ? trim($request->string('empresa')->toString()) : 'todas',
            'por_mes' => $porMes,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'meses' => $schema->integer()
                ->description('Quantos meses para trás considerar (1 a 60). Padrão: 12.'),
            'empresa' => $schema->string()
                ->description('Filtro opcional: nome da empresa (parcial serve), ou "sem empresa" para só a vida pessoal.'),
        ];
    }
}
