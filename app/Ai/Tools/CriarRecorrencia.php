<?php

namespace App\Ai\Tools;

use App\Filament\Resources\Lancamentos\LancamentoResource;
use App\Models\Account;
use App\Models\Company;
use App\Models\RecurringTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

/**
 * Cria uma recorrência mensal (assinatura, aluguel, contrato) — o gerador
 * diário transforma cada vencimento em lançamento automaticamente.
 * SEMPRE exige aprovação do usuário antes de executar.
 */
class CriarRecorrencia extends MilhaTool implements Approvable
{
    use InteractsWithApprovals;

    public function description(): string
    {
        return 'Cria uma recorrência mensal (receita ou despesa que se repete todo mês, como '
            .'assinatura, aluguel ou contrato). Todo mês, no dia do vencimento, vira um '
            .'lançamento automático. A execução SEMPRE pede confirmação do usuário. Antes de '
            .'chamar, confirme: tipo, valor mensal, dia do vencimento e descrição.';
    }

    public function handle(Request $request): string
    {
        $tipo = $request->string('tipo')->toString();
        $descricao = trim($request->string('descricao')->toString());
        $valor = round($request->float('valor_mensal'), 2);
        $dia = $request->integer('dia_do_mes', 5);
        $inicio = $request->string('inicio')->toString() ?: now()->startOfMonth()->toDateString();
        $fim = $request->string('fim')->toString() ?: null;

        if (! in_array($tipo, ['INCOME', 'EXPENSE'], true)) {
            return $this->json(['erro' => 'tipo deve ser INCOME (receita) ou EXPENSE (despesa).']);
        }

        if ($descricao === '' || mb_strlen($descricao) > 255) {
            return $this->json(['erro' => 'descricao é obrigatória (até 255 caracteres).']);
        }

        if ($valor <= 0) {
            return $this->json(['erro' => 'valor_mensal deve ser maior que zero.']);
        }

        if ($dia < 1 || $dia > 31) {
            return $this->json(['erro' => 'dia_do_mes deve estar entre 1 e 31.']);
        }

        foreach (array_filter(['inicio' => $inicio, 'fim' => $fim]) as $campo => $data) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || strtotime($data) === false) {
                return $this->json(['erro' => "{$campo} deve estar no formato YYYY-MM-DD."]);
            }
        }

        if ($fim !== null && $fim < $inicio) {
            return $this->json(['erro' => 'fim não pode ser anterior ao inicio.']);
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

        $company = null;

        if ($request->filled('empresa')) {
            $nome = trim($request->string('empresa')->toString());

            // lower() dos dois lados: LIKE é case-sensitive no PostgreSQL.
            $company = Company::query()
                ->where('tenant_id', $this->tenant->getKey())
                ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($nome).'%'])
                ->first();

            if ($company === null) {
                return $this->json(['erro' => "Empresa \"{$nome}\" não encontrada. Use ConsultarEmpresas."]);
            }
        }

        $recorrencia = RecurringTransaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'type' => $tipo,
            'description' => $descricao,
            'amount' => $valor,
            'day_of_month' => $dia,
            'starts_on' => $inicio,
            'ends_on' => $fim,
            'category' => $categoria,
            'account_id' => $account?->getKey(),
            'company_id' => $company?->getKey(),
            'active' => true,
        ]);

        return $this->json([
            'sucesso' => true,
            'recorrencia' => array_filter([
                'id' => $recorrencia->getKey(),
                'tipo' => $tipo === 'EXPENSE' ? 'Despesa' : 'Receita',
                'descricao' => $descricao,
                'valor_mensal' => $this->money($valor),
                'dia_do_vencimento' => $dia,
                'inicio' => $inicio,
                'fim' => $fim,
                'categoria' => $categoria,
                'conta' => $account?->name,
                'empresa' => $company?->name,
            ], fn ($v) => $v !== null),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()->enum(['INCOME', 'EXPENSE'])
                ->description('INCOME para receita recorrente, EXPENSE para despesa recorrente.')
                ->required(),
            'descricao' => $schema->string()
                ->description('Descrição, ex.: "Assinatura Netflix", "Aluguel galpão — Cliente Y".')
                ->required(),
            'valor_mensal' => $schema->number()
                ->description('Valor mensal em reais, sempre positivo.')
                ->required(),
            'dia_do_mes' => $schema->integer()
                ->description('Dia do vencimento (1 a 31; meses curtos caem no último dia). Padrão: 5.'),
            'inicio' => $schema->string()
                ->description('Data de início, YYYY-MM-DD. Padrão: primeiro dia do mês atual.'),
            'fim' => $schema->string()
                ->description('Data de fim, YYYY-MM-DD. Omita para contrato sem prazo.'),
            'categoria' => $schema->string()
                ->enum(array_keys(LancamentoResource::CATEGORIES))
                ->description('Categoria opcional.'),
            'account_id' => $schema->integer()
                ->description('Opcional: id da conta (de ConsultarContas) que os lançamentos vão movimentar.'),
            'empresa' => $schema->string()
                ->description('Opcional: nome da empresa dona da recorrência.'),
        ];
    }
}
