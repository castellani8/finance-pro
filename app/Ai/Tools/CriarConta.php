<?php

namespace App\Ai\Tools;

use App\Models\Account;
use App\Models\Company;
use App\Models\PortfolioSnapshot;
use App\Support\PortfolioCache;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

/**
 * Cria uma conta (banco, corretora, caixa) — espelha a tela de Contas.
 * SEMPRE exige aprovação do usuário antes de executar.
 */
class CriarConta extends MilhaTool implements Approvable
{
    use InteractsWithApprovals;

    public function description(): string
    {
        return 'Cria uma conta nova (bancária, corretora, caixa/dinheiro físico ou outra) '
            .'com saldo inicial. O saldo entra no patrimônio e os lançamentos vinculados '
            .'movimentam a partir dele. A execução SEMPRE pede confirmação do usuário. '
            .'Antes de chamar, confirme nome, tipo e saldo inicial.';
    }

    public function handle(Request $request): string
    {
        $nome = trim($request->string('nome')->toString());
        $tipo = $request->string('tipo')->toString() ?: 'bank';
        $moeda = $request->string('moeda')->toString() ?: 'BRL';
        $saldoInicial = round($request->float('saldo_inicial'), 2);

        if ($nome === '' || mb_strlen($nome) > 100) {
            return $this->json(['erro' => 'nome é obrigatório (até 100 caracteres).']);
        }

        if (! array_key_exists($tipo, Account::KIND_LABELS)) {
            return $this->json(['erro' => 'tipo inválido.', 'tipos_validos' => Account::KIND_LABELS]);
        }

        if (! in_array($moeda, ['BRL', 'USD', 'EUR'], true)) {
            return $this->json(['erro' => 'moeda deve ser BRL, USD ou EUR.']);
        }

        $duplicada = Account::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->where('name', $nome)
            ->exists();

        if ($duplicada) {
            return $this->json(['erro' => "Já existe uma conta chamada \"{$nome}\"."]);
        }

        $company = null;

        if ($request->filled('empresa')) {
            $nomeEmpresa = trim($request->string('empresa')->toString());

            // lower() dos dois lados: LIKE é case-sensitive no PostgreSQL.
            $company = Company::query()
                ->where('tenant_id', $this->tenant->getKey())
                ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($nomeEmpresa).'%'])
                ->first();

            if ($company === null) {
                return $this->json(['erro' => "Empresa \"{$nomeEmpresa}\" não encontrada. Use ConsultarEmpresas."]);
            }
        }

        $account = Account::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => $nome,
            'kind' => $tipo,
            'currency' => $moeda,
            'opening_balance' => $saldoInicial,
            'company_id' => $company?->getKey(),
        ]);

        PortfolioSnapshot::where('tenant_id', $this->tenant->getKey())->delete();
        PortfolioCache::bump($this->tenant->getKey());

        return $this->json([
            'sucesso' => true,
            'conta' => array_filter([
                'id' => $account->getKey(),
                'nome' => $nome,
                'tipo' => Account::KIND_LABELS[$tipo],
                'moeda' => $moeda,
                'saldo_inicial' => number_format($saldoInicial, 2, ',', '.'),
                'empresa' => $company?->name,
            ], fn ($v) => $v !== null),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'nome' => $schema->string()
                ->description('Nome da conta, ex.: "Nubank", "XP", "Caixa da fazenda".')
                ->required(),
            'tipo' => $schema->string()->enum(array_keys(Account::KIND_LABELS))
                ->description('bank (conta bancária), broker (corretora), cash (dinheiro físico) ou other. Padrão: bank.'),
            'moeda' => $schema->string()->enum(['BRL', 'USD', 'EUR'])
                ->description('Moeda da conta. Padrão: BRL.'),
            'saldo_inicial' => $schema->number()
                ->description('Saldo de partida em unidades da moeda. Padrão: 0.'),
            'empresa' => $schema->string()
                ->description('Opcional: nome da empresa dona da conta.'),
        ];
    }
}
