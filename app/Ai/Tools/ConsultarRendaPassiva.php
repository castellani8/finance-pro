<?php

namespace App\Ai\Tools;

use App\Services\PassiveIncome;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Renda passiva mês a mês (aluguéis, proventos, juros) e progresso da meta.
 */
class ConsultarRendaPassiva extends MilhaTool
{
    public function description(): string
    {
        return 'Renda passiva mensal do usuário (aluguéis, proventos e juros) nos últimos '
            .'meses, com média mensal e progresso em relação à meta de "viver de renda".';
    }

    public function handle(Request $request): string
    {
        $months = min(60, max(1, $request->integer('meses', 12)));

        $data = app(PassiveIncome::class)->monthly($this->tenant, $months);

        // Chaves YYYY-MM: os labels do serviço ("mai/26") são para gráfico;
        // para o modelo, o formato ISO elimina ambiguidade.
        $cursor = now()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $porMes = [];

        foreach ($data['labels'] as $i => $label) {
            $porMes[$cursor->format('Y-m')] = [
                'alugueis' => $this->money($data['alugueis'][$i]),
                'proventos' => $this->money($data['proventos'][$i]),
                'juros' => $this->money($data['juros'][$i]),
                'total' => $this->money($data['total'][$i]),
            ];

            $cursor = $cursor->addMonthNoOverflow();
        }

        $media = count($data['total']) > 0
            ? array_sum($data['total']) / count($data['total'])
            : 0.0;

        $meta = $this->tenant->passive_income_goal !== null
            ? (float) $this->tenant->passive_income_goal
            : null;

        return $this->json([
            'meses_considerados' => $months,
            'media_mensal' => $this->money($media),
            'meta_mensal' => $meta !== null ? $this->money($meta) : 'não definida',
            'progresso_da_meta_pct' => ($meta !== null && $meta > 0)
                ? round($media / $meta * 100, 1)
                : null,
            'por_mes' => $porMes,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'meses' => $schema->integer()
                ->description('Quantos meses para trás considerar (1 a 60). Padrão: 12.'),
        ];
    }
}
