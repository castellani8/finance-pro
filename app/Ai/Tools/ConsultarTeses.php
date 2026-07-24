<?php

namespace App\Ai\Tools;

use App\Models\Asset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Diário de tese: por que o usuário comprou cada ativo, o preço na época do
 * registro e a variação desde então — matéria-prima do coach comportamental.
 */
class ConsultarTeses extends MilhaTool
{
    public function description(): string
    {
        return 'Consulta as teses de investimento que o usuário escreveu nos ativos (por que comprou), '
            .'com o preço na época do registro e a variação até hoje. Use quando perguntarem "por que '
            .'comprei X?" ou para lembrar o usuário da tese quando ele falar em vender no impulso.';
    }

    public function handle(Request $request): string
    {
        $ticker = $request->filled('ticker') ? mb_strtoupper(trim($request->string('ticker'))) : null;

        $assets = Asset::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->when($ticker, fn ($q) => $q->where('ticker_or_code', $ticker))
            ->get()
            ->filter(fn (Asset $asset): bool => filled($asset->metadata['thesis'] ?? null));

        if ($assets->isEmpty()) {
            return $this->json([
                'teses' => [],
                'dica' => $ticker
                    ? "Nenhuma tese registrada para {$ticker}. O usuário pode escrever uma na seção \"Tese de investimento\" do ativo."
                    : 'Nenhuma tese registrada ainda. Sugira registrar o "porquê" de cada compra na seção "Tese de investimento" do ativo.',
            ]);
        }

        return $this->json([
            'teses' => $assets->map(function (Asset $asset): array {
                $thesisPrice = is_numeric($asset->metadata['thesis_price'] ?? null)
                    ? (float) $asset->metadata['thesis_price']
                    : null;
                $current = $asset->currentUnitPrice();

                return array_filter([
                    'ativo' => $asset->name,
                    'ticker' => $asset->ticker_or_code,
                    'tese' => $asset->metadata['thesis'],
                    'registrada_em' => $asset->metadata['thesis_recorded_at'] ?? null,
                    'preco_na_epoca' => $thesisPrice !== null ? $this->money($thesisPrice) : null,
                    'preco_atual' => $current !== null ? $this->money($current) : null,
                    'variacao_desde_a_tese_pct' => ($thesisPrice > 0 && $current !== null)
                        ? round(($current / $thesisPrice - 1) * 100, 1)
                        : null,
                ], fn ($v) => $v !== null);
            })->values()->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ticker' => $schema->string()
                ->description('Filtra por ticker/código de um ativo específico. Opcional — sem ele, lista todas as teses.'),
        ];
    }
}
