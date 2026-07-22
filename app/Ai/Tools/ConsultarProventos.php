<?php

namespace App\Ai\Tools;

use App\Models\Asset;
use App\Models\Transaction;
use App\Services\CurrencyConverter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Proventos (dividendos, JCP, rendimentos, juros, amortizações) por período,
 * com filtros opcionais por tipo e por ativo. Soma com sinal: estornos em
 * débito subtraem, como na tela de Proventos.
 */
class ConsultarProventos extends MilhaTool
{
    private const TYPE_LABELS = [
        'DIVIDEND' => 'Dividendo',
        'JCP' => 'JCP',
        'INCOME' => 'Rendimento',
        'INTEREST' => 'Juros',
        'AMORTIZATION' => 'Amortização',
        'FRACTION_AUCTION' => 'Leilão de fração',
    ];

    public function description(): string
    {
        return 'Consulta os proventos recebidos (dividendos, JCP, rendimentos, juros, '
            .'amortizações) em um período, com total em reais, quebra por tipo e por ativo. '
            .'Aceita filtro opcional por tipo de provento e por ticker do ativo.';
    }

    public function handle(Request $request): string
    {
        $inicio = $request->string('data_inicio')->toString();
        $fim = $request->string('data_fim')->toString();

        foreach (['data_inicio' => $inicio, 'data_fim' => $fim] as $campo => $valor) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                return $this->json(['erro' => "Parâmetro {$campo} deve estar no formato YYYY-MM-DD."]);
            }
        }

        $rows = Transaction::query()
            ->where('transactions.tenant_id', $this->tenant->getKey())
            ->whereIn('transactions.type', Asset::CASH_INCOME_TYPES)
            ->whereNotNull('asset_id')
            ->whereBetween('transactions.transaction_date', [$inicio, $fim])
            ->when($request->filled('tipo'), fn ($q) => $q->where('transactions.type', $request->string('tipo')->toString()))
            ->when($request->filled('ticker'), fn ($q) => $q->whereIn('asset_id', fn ($sub) => $sub
                ->select('id')->from('assets')
                ->where('tenant_id', $this->tenant->getKey())
                ->where('ticker_or_code', mb_strtoupper($request->string('ticker')->toString()))))
            ->with('asset')
            ->get();

        $converter = app(CurrencyConverter::class);

        $signedBrl = fn (Transaction $t): float => $t->flowDirection()->sign() * $converter->toBrl(
            (float) $t->total_amount,
            $t->asset?->currency ?? 'BRL',
            $t->transaction_date->toDateString(),
        );

        return $this->json([
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'total' => $this->money($rows->sum($signedBrl)),
            'quantidade_de_pagamentos' => $rows->count(),
            'por_tipo' => $rows
                ->groupBy('type')
                ->map(fn ($group) => $this->money($group->sum($signedBrl)))
                ->mapWithKeys(fn ($v, $k) => [self::TYPE_LABELS[$k] ?? $k => $v])
                ->all(),
            'por_ativo' => $rows
                ->groupBy(fn (Transaction $t): string => $t->asset?->ticker_or_code ?? $t->asset?->name ?? '—')
                ->map(fn ($group) => $group->sum($signedBrl))
                ->sortDesc()
                ->take(15)
                ->map(fn (float $v) => $this->money($v))
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'data_inicio' => $schema->string()
                ->description('Início do período, formato YYYY-MM-DD.')
                ->required(),
            'data_fim' => $schema->string()
                ->description('Fim do período (inclusivo), formato YYYY-MM-DD.')
                ->required(),
            'tipo' => $schema->string()
                ->enum(array_keys(self::TYPE_LABELS))
                ->description('Filtro opcional por tipo de provento.'),
            'ticker' => $schema->string()
                ->description('Filtro opcional por ticker/código do ativo, ex.: PETR4, MXRF11.'),
        ];
    }
}
