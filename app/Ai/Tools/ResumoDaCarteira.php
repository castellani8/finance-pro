<?php

namespace App\Ai\Tools;

use App\Models\Asset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Visão geral da carteira: totais, rentabilidade e posições abertas.
 */
class ResumoDaCarteira extends MilhaTool
{
    public function description(): string
    {
        return 'Resumo da carteira de investimentos e patrimônio do usuário: '
            .'valor investido, valor atual, proventos recebidos, distribuição '
            .'por classe de ativo e as posições abertas com seus valores.';
    }

    public function handle(Request $request): string
    {
        $assets = Asset::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->with('transactions')
            ->get();

        Asset::primeMarketData($assets);

        $positions = $assets
            ->filter(fn (Asset $a): bool => $a->positionQuantity() > 0 || $a->currentValue() > 0)
            ->sortByDesc(fn (Asset $a): float => $a->currentValue())
            ->values();

        $invested = $positions->sum(fn (Asset $a): float => $a->purchaseValue());
        $current = $positions->sum(fn (Asset $a): float => $a->currentValue());
        $dividends = $assets->sum(fn (Asset $a): float => $a->dividendsReceived());

        return $this->json([
            'valor_investido' => $this->money($invested),
            'valor_atual' => $this->money($current),
            'proventos_recebidos_total' => $this->money($dividends),
            'rentabilidade_sem_proventos_pct' => $invested > 0
                ? round(($current - $invested) / $invested * 100, 2)
                : null,
            'por_classe' => $positions
                ->groupBy(fn (Asset $a): string => Asset::TYPE_LABELS[$a->type] ?? $a->type)
                ->map(fn ($group) => $this->money($group->sum(fn (Asset $a): float => $a->currentValue())))
                ->all(),
            'posicoes' => $positions
                ->take(30)
                ->map(fn (Asset $a): array => array_filter([
                    'nome' => $a->name,
                    'ticker' => $a->ticker_or_code,
                    'classe' => Asset::TYPE_LABELS[$a->type] ?? $a->type,
                    'quantidade' => round($a->positionQuantity(), 6),
                    'valor_atual' => $this->money($a->currentValue()),
                    'moeda' => $a->currency,
                ], fn ($v) => $v !== null))
                ->all(),
            'posicoes_omitidas' => max(0, $positions->count() - 30),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
