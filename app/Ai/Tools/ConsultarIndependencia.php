<?php

namespace App\Ai\Tools;

use App\Services\FinancialIndependence;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Projeção de independência financeira ("viver de renda") do usuário, com
 * cenário alternativo de aporte extra para a Milha simular na conversa.
 */
class ConsultarIndependencia extends MilhaTool
{
    public function description(): string
    {
        return 'Consulta a projeção de independência financeira do usuário: quando a renda passiva '
            .'projetada cobre o custo de vida, cobertura atual, patrimônio alvo e o plano configurado. '
            .'Aceita um aporte mensal extra opcional para simular cenários ("e se eu aportasse mais X?").';
    }

    public function handle(Request $request): string
    {
        $extra = $request->filled('aporte_extra') ? max(0.0, $request->float('aporte_extra')) : null;

        $data = app(FinancialIndependence::class)->build($this->tenant, $extra);

        if (! $data['configurado']) {
            return $this->json([
                'configurado' => false,
                'renda_passiva_media_mensal' => $this->money($data['renda_media_mensal']),
                'patrimonio_atual' => $this->money($data['patrimonio_atual']),
                'dica' => 'O usuário ainda não definiu o plano (custo de vida mensal). Sugira definir na página "Viver de Renda".',
            ]);
        }

        return $this->json(array_filter([
            'configurado' => true,
            'custo_de_vida_mensal' => $this->money((float) $data['custo_mensal']),
            'aporte_mensal_considerado' => $this->money($data['aporte_mensal']),
            'retorno_real_esperado_aa' => $data['retorno_anual'].'%',
            'patrimonio_atual' => $this->money($data['patrimonio_atual']),
            'patrimonio_alvo' => $data['patrimonio_alvo'] !== null ? $this->money($data['patrimonio_alvo']) : null,
            'renda_passiva_media_mensal' => $this->money($data['renda_media_mensal']),
            'cobertura_do_custo_pct' => $data['cobertura_pct'],
            'meses_ate_independencia' => $data['meses_ate_independencia'],
            'data_projetada_independencia' => $data['data_independencia'],
            'observacao' => 'Projeção baseada no yield atual da carteira e no retorno esperado — não é promessa de retorno.',
        ], fn ($v) => $v !== null));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'aporte_extra' => $schema->number()
                ->description('Aporte mensal EXTRA (além do plano) em reais, para simular um cenário. Opcional.'),
        ];
    }
}
