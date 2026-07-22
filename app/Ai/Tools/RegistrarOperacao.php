<?php

namespace App\Ai\Tools;

use App\Enums\FlowDirection;
use App\Models\Account;
use App\Models\Asset;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Support\PortfolioCache;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

/**
 * Compra ou venda de um ativo de investimento ("comprei 2 PETR4 a R$ 40").
 * Se o ativo ainda não existir, cria junto — tudo numa única aprovação.
 * SEMPRE exige aprovação do usuário antes de executar.
 */
class RegistrarOperacao extends MilhaTool implements Approvable
{
    use InteractsWithApprovals;

    private const INVESTMENT_TYPES = ['STOCK', 'FII', 'FIXED_INCOME', 'OPTION'];

    public function description(): string
    {
        return 'Registra uma COMPRA ou VENDA de ativo de investimento (ação, FII, renda fixa, '
            .'opção) na carteira. Se o ativo ainda não existir, informe tipo_ativo para criá-lo '
            .'na mesma operação. A execução SEMPRE pede confirmação do usuário. A corretora vai '
            .'em "instituicao"; se o dinheiro deve sair/entrar numa conta cadastrada, descubra o '
            .'account_id com ConsultarContas. Antes de chamar, confirme quantidade, preço e data.';
    }

    public function handle(Request $request): string
    {
        $operacao = $request->string('operacao')->toString();
        $ticker = mb_strtoupper(trim($request->string('ticker')->toString()));
        $quantidade = round($request->float('quantidade'), 6);
        $precoUnitario = round($request->float('preco_unitario'), 4);
        $data = $request->string('data')->toString();

        if (! in_array($operacao, ['BUY', 'SELL'], true)) {
            return $this->json(['erro' => 'operacao deve ser BUY (compra) ou SELL (venda).']);
        }

        if ($ticker === '') {
            return $this->json(['erro' => 'ticker é obrigatório (ex.: PETR4, MXRF11, ou o código do CDB).']);
        }

        if ($quantidade <= 0) {
            return $this->json(['erro' => 'quantidade deve ser maior que zero.']);
        }

        if ($precoUnitario <= 0) {
            return $this->json(['erro' => 'preco_unitario deve ser maior que zero.']);
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || strtotime($data) === false) {
            return $this->json(['erro' => 'data deve estar no formato YYYY-MM-DD.']);
        }

        $account = null;

        if ($request->filled('account_id')) {
            $account = Account::query()
                ->where('tenant_id', $this->tenant->getKey())
                ->find($request->integer('account_id'));

            if ($account === null) {
                return $this->json(['erro' => 'account_id não encontrado. Use ConsultarContas para listar as contas.']);
            }
        }

        $asset = Asset::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->where('ticker_or_code', $ticker)
            ->first();

        $criouAtivo = false;

        if ($asset === null) {
            if ($operacao === 'SELL') {
                return $this->json(['erro' => "Não existe o ativo {$ticker} na carteira para vender."]);
            }

            $tipoAtivo = $request->string('tipo_ativo')->toString();

            if (! in_array($tipoAtivo, self::INVESTMENT_TYPES, true)) {
                return $this->json([
                    'erro' => "O ativo {$ticker} ainda não existe na carteira. Para criar junto com a compra, "
                        .'informe tipo_ativo (STOCK, FII, FIXED_INCOME ou OPTION). Confirme o tipo com o usuário.',
                ]);
            }

            $asset = Asset::create([
                'tenant_id' => $this->tenant->getKey(),
                'name' => trim($request->string('nome_ativo')->toString()) ?: $ticker,
                'type' => $tipoAtivo,
                'ticker_or_code' => $ticker,
                'currency' => 'BRL',
            ]);

            $criouAtivo = true;
        }

        if ($operacao === 'SELL') {
            $posicao = $asset->load('transactions')->positionQuantity();

            if ($quantidade > $posicao + 1e-9) {
                return $this->json([
                    'erro' => "Venda maior que a posição atual: o usuário tem {$posicao} de {$ticker}. Confirme com ele.",
                ]);
            }
        }

        $total = round($quantidade * $precoUnitario, 4);

        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'account_id' => $account?->getKey(),
            'type' => $operacao,
            'transaction_date' => $data,
            'quantity' => $quantidade,
            'unit_price' => $precoUnitario,
            'total_amount' => $total,
            'direction' => FlowDirection::defaultForType($operacao)->value,
            'movement' => $operacao === 'BUY' ? 'Compra' : 'Venda',
            'institution' => trim($request->string('instituicao')->toString()) ?: null,
            'source' => 'manual',
        ]);

        PortfolioSnapshot::where('tenant_id', $this->tenant->getKey())->delete();
        PortfolioCache::bump($this->tenant->getKey());

        return $this->json([
            'sucesso' => true,
            'ativo_criado_junto' => $criouAtivo,
            'operacao' => array_filter([
                'id' => $transaction->getKey(),
                'tipo' => $operacao === 'BUY' ? 'Compra' : 'Venda',
                'ativo' => $ticker,
                'quantidade' => $quantidade,
                'preco_unitario' => $this->money($precoUnitario),
                'total' => $this->money($total),
                'data' => $data,
                'corretora' => $transaction->institution,
                'conta' => $account?->name,
            ], fn ($v) => $v !== null),
            'posicao_atual' => round($asset->load('transactions')->positionQuantity(), 6),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'operacao' => $schema->string()->enum(['BUY', 'SELL'])
                ->description('BUY para compra, SELL para venda.')
                ->required(),
            'ticker' => $schema->string()
                ->description('Ticker/código do ativo, ex.: PETR4, MXRF11.')
                ->required(),
            'quantidade' => $schema->number()
                ->description('Quantidade negociada (aceita fração).')
                ->required(),
            'preco_unitario' => $schema->number()
                ->description('Preço por unidade em reais.')
                ->required(),
            'data' => $schema->string()
                ->description('Data da operação, YYYY-MM-DD.')
                ->required(),
            'instituicao' => $schema->string()
                ->description('Corretora/instituição onde a operação foi feita, ex.: "NuInvest", "XP". Opcional.'),
            'account_id' => $schema->integer()
                ->description('Opcional: id da conta (de ConsultarContas) de onde o dinheiro sai/entra.'),
            'tipo_ativo' => $schema->string()->enum(self::INVESTMENT_TYPES)
                ->description('Obrigatório apenas se o ativo ainda não existir: STOCK (ação), FII, FIXED_INCOME ou OPTION.'),
            'nome_ativo' => $schema->string()
                ->description('Nome do ativo caso precise ser criado. Padrão: o próprio ticker.'),
        ];
    }
}
