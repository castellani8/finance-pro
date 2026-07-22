<?php

namespace App\Ai\Tools;

use App\Enums\FlowDirection;
use App\Filament\Resources\Lancamentos\LancamentoResource;
use App\Models\Account;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Support\PortfolioCache;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

/**
 * Cria um lançamento avulso (receita/despesa sem ativo) — espelha o fluxo da
 * tela de Lançamentos. SEMPRE exige aprovação do usuário antes de executar
 * (needsApproval padrão do trait), e o handle() só roda após o "sim".
 */
class CriarLancamento extends MilhaTool implements Approvable
{
    use InteractsWithApprovals;

    public function description(): string
    {
        return 'Cria um lançamento avulso (receita ou despesa do dia a dia, sem vínculo '
            .'com ativo). A execução SEMPRE pede confirmação do usuário. Antes de chamar, '
            .'confirme com o usuário: tipo, valor, data e descrição. Se quiser vincular a '
            .'uma conta, descubra o account_id com a tool ConsultarContas.';
    }

    public function handle(Request $request): string
    {
        $tipo = $request->string('tipo')->toString();
        $data = $request->string('data')->toString();
        $descricao = trim($request->string('descricao')->toString());
        $valor = round($request->float('valor'), 2);

        if (! in_array($tipo, ['INCOME', 'EXPENSE'], true)) {
            return $this->json(['erro' => 'tipo deve ser INCOME (receita) ou EXPENSE (despesa).']);
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || strtotime($data) === false) {
            return $this->json(['erro' => 'data deve estar no formato YYYY-MM-DD.']);
        }

        if ($descricao === '' || mb_strlen($descricao) > 255) {
            return $this->json(['erro' => 'descricao é obrigatória (até 255 caracteres).']);
        }

        if ($valor <= 0) {
            return $this->json(['erro' => 'valor deve ser maior que zero.']);
        }

        $categoria = $request->string('categoria')->toString() ?: null;

        if ($categoria !== null && ! array_key_exists($categoria, LancamentoResource::CATEGORIES)) {
            return $this->json([
                'erro' => 'categoria inválida.',
                'categorias_validas' => array_keys(LancamentoResource::CATEGORIES),
            ]);
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

        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => null,
            'type' => $tipo,
            'transaction_date' => $data,
            'movement' => $descricao,
            'quantity' => 1,
            'total_amount' => $valor,
            'direction' => FlowDirection::defaultForType($tipo)->value,
            'category' => $categoria,
            'account_id' => $account?->getKey(),
            'notes' => $request->string('observacoes')->toString() ?: null,
        ]);

        PortfolioSnapshot::where('tenant_id', $this->tenant->getKey())->delete();
        PortfolioCache::bump($this->tenant->getKey());

        return $this->json([
            'sucesso' => true,
            'lancamento' => [
                'id' => $transaction->getKey(),
                'tipo' => $tipo === 'EXPENSE' ? 'Despesa' : 'Receita',
                'data' => $data,
                'descricao' => $descricao,
                'valor' => $this->money($valor),
                'categoria' => $categoria,
                'conta' => $account?->name,
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()->enum(['INCOME', 'EXPENSE'])
                ->description('INCOME para receita, EXPENSE para despesa.')
                ->required(),
            'data' => $schema->string()
                ->description('Data do lançamento, formato YYYY-MM-DD.')
                ->required(),
            'descricao' => $schema->string()
                ->description('Descrição curta do lançamento, ex.: "Assinatura Netflix".')
                ->required(),
            'valor' => $schema->number()
                ->description('Valor em reais, sempre positivo (o tipo define se entra ou sai).')
                ->required(),
            'categoria' => $schema->string()
                ->enum(array_keys(LancamentoResource::CATEGORIES))
                ->description('Categoria opcional do lançamento.'),
            'account_id' => $schema->integer()
                ->description('Opcional: id da conta (de ConsultarContas) para o valor entrar/sair do saldo dela.'),
            'observacoes' => $schema->string()
                ->description('Observações opcionais.'),
        ];
    }
}
