<?php

namespace Tests\Feature;

use App\Ai\Tools\CadastrarAtivo;
use App\Ai\Tools\ConsultarContas;
use App\Ai\Tools\CriarConta;
use App\Ai\Tools\ConsultarFluxoDeCaixa;
use App\Ai\Tools\ConsultarLancamentos;
use App\Ai\Tools\ConsultarProventos;
use App\Ai\Tools\CriarLancamento;
use App\Ai\Tools\CriarRecorrencia;
use App\Ai\Tools\RegistrarOperacao;
use App\Ai\Tools\ResumoDaCarteira;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Company;
use App\Models\RecurringTransaction;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * As tools da Milha são a fronteira de segurança do assistente: tudo que o
 * modelo enxerga ou escreve passa por aqui. Estes testes garantem o escopo
 * por tenant, as validações de escrita e a exigência de aprovação.
 */
class MilhaToolsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant('Tenant A');
        $this->outroTenant = $this->makeTenant('Tenant B');
    }

    private function makeTenant(string $name): Tenant
    {
        $tenant = new Tenant;
        $tenant->forceFill(['name' => $name, 'uuid' => (string) Str::uuid()])->save();

        return $tenant;
    }

    private function seedProvento(Tenant $tenant, string $ticker, string $date, float $amount, string $direction = 'Credito'): Asset
    {
        $asset = Asset::firstOrCreate(
            ['tenant_id' => $tenant->getKey(), 'ticker_or_code' => $ticker],
            ['name' => $ticker, 'type' => 'STOCK'],
        );

        Transaction::create([
            'tenant_id' => $tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'type' => 'DIVIDEND',
            'transaction_date' => $date,
            'quantity' => 10,
            'total_amount' => $amount,
            'direction' => $direction,
            'source' => 'manual',
        ]);

        return $asset;
    }

    public function test_consultar_proventos_soma_com_sinal_e_nao_vaza_outro_tenant(): void
    {
        $this->seedProvento($this->tenant, 'PETR4', '2026-07-10', 100.00);
        $this->seedProvento($this->tenant, 'PETR4', '2026-07-15', 30.00, 'Debito'); // estorno
        $this->seedProvento($this->outroTenant, 'VALE3', '2026-07-12', 999.00);

        $result = json_decode(
            (new ConsultarProventos($this->tenant))->handle(new Request([
                'data_inicio' => '2026-07-01',
                'data_fim' => '2026-07-31',
            ])),
            true,
        );

        $this->assertSame('R$ 70,00', $result['total']);
        $this->assertArrayHasKey('PETR4', $result['por_ativo']);
        $this->assertArrayNotHasKey('VALE3', $result['por_ativo']);
    }

    public function test_consultar_proventos_valida_formato_de_data(): void
    {
        $result = json_decode(
            (new ConsultarProventos($this->tenant))->handle(new Request([
                'data_inicio' => '10/07/2026',
                'data_fim' => '2026-07-31',
            ])),
            true,
        );

        $this->assertArrayHasKey('erro', $result);
    }

    public function test_resumo_da_carteira_so_ve_ativos_do_tenant(): void
    {
        $this->seedProvento($this->tenant, 'PETR4', '2026-07-10', 100.00);
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => Asset::where('ticker_or_code', 'PETR4')->where('tenant_id', $this->tenant->getKey())->value('id'),
            'type' => 'BUY',
            'transaction_date' => '2026-01-10',
            'quantity' => 10,
            'unit_price' => 30,
            'total_amount' => 300,
            'direction' => 'Credito',
            'source' => 'manual',
        ]);
        $this->seedProvento($this->outroTenant, 'VALE3', '2026-07-12', 999.00);

        $result = json_decode((new ResumoDaCarteira($this->tenant))->handle(new Request), true);

        $tickers = array_column($result['posicoes'], 'ticker');

        $this->assertContains('PETR4', $tickers);
        $this->assertNotContains('VALE3', $tickers);
    }

    public function test_consultar_contas_lista_apenas_contas_do_tenant(): void
    {
        Account::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Nubank', 'kind' => 'bank', 'opening_balance' => 100]);
        Account::create(['tenant_id' => $this->outroTenant->getKey(), 'name' => 'Conta Alheia', 'kind' => 'bank', 'opening_balance' => 999]);

        $result = json_decode((new ConsultarContas($this->tenant))->handle(new Request), true);

        $nomes = array_column($result['contas'], 'nome');

        $this->assertSame(['Nubank'], $nomes);
    }

    public function test_criar_lancamento_cria_com_direcao_e_tenant_corretos(): void
    {
        $result = json_decode(
            (new CriarLancamento($this->tenant))->handle(new Request([
                'tipo' => 'EXPENSE',
                'data' => '2026-07-20',
                'descricao' => 'Conta de luz',
                'valor' => 250.55,
            ])),
            true,
        );

        $this->assertTrue($result['sucesso']);

        $transaction = Transaction::find($result['lancamento']['id']);

        $this->assertSame($this->tenant->getKey(), $transaction->tenant_id);
        $this->assertSame('Debito', $transaction->direction);
        $this->assertNull($transaction->asset_id);
    }

    public function test_criar_lancamento_rejeita_dados_invalidos(): void
    {
        $tool = new CriarLancamento($this->tenant);

        $casos = [
            ['tipo' => 'TRANSFER', 'data' => '2026-07-20', 'descricao' => 'x', 'valor' => 10],
            ['tipo' => 'INCOME', 'data' => '20/07/2026', 'descricao' => 'x', 'valor' => 10],
            ['tipo' => 'INCOME', 'data' => '2026-07-20', 'descricao' => '', 'valor' => 10],
            ['tipo' => 'INCOME', 'data' => '2026-07-20', 'descricao' => 'x', 'valor' => 0],
            ['tipo' => 'INCOME', 'data' => '2026-07-20', 'descricao' => 'x', 'valor' => 10, 'categoria' => 'Categoria Inventada'],
        ];

        foreach ($casos as $caso) {
            $result = json_decode($tool->handle(new Request($caso)), true);
            $this->assertArrayHasKey('erro', $result, 'Deveria rejeitar: '.json_encode($caso));
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_criar_lancamento_rejeita_conta_de_outro_tenant(): void
    {
        $contaAlheia = Account::create([
            'tenant_id' => $this->outroTenant->getKey(),
            'name' => 'Conta Alheia',
            'kind' => 'bank',
            'opening_balance' => 0,
        ]);

        $result = json_decode(
            (new CriarLancamento($this->tenant))->handle(new Request([
                'tipo' => 'EXPENSE',
                'data' => '2026-07-20',
                'descricao' => 'Tentativa de vazamento',
                'valor' => 10,
                'account_id' => $contaAlheia->getKey(),
            ])),
            true,
        );

        $this->assertArrayHasKey('erro', $result);
        $this->assertSame(0, Transaction::count());
    }

    public function test_cadastrar_ativo_rejeita_ticker_duplicado_e_tipo_invalido(): void
    {
        Asset::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Petrobras', 'type' => 'STOCK', 'ticker_or_code' => 'PETR4']);

        $tool = new CadastrarAtivo($this->tenant);

        $duplicado = json_decode($tool->handle(new Request(['tipo' => 'STOCK', 'nome' => 'Petro de novo', 'ticker' => 'petr4'])), true);
        $this->assertArrayHasKey('erro', $duplicado);

        $tipoInvalido = json_decode($tool->handle(new Request(['tipo' => 'CRYPTO', 'nome' => 'Bitcoin'])), true);
        $this->assertArrayHasKey('erro', $tipoInvalido);
    }

    public function test_cadastrar_ativo_fisico_exige_valor_e_cria_aquisicao(): void
    {
        $tool = new CadastrarAtivo($this->tenant);

        $semValor = json_decode($tool->handle(new Request(['tipo' => 'VEHICLE', 'nome' => 'Hilux 2024'])), true);
        $this->assertArrayHasKey('erro', $semValor);

        $ok = json_decode($tool->handle(new Request([
            'tipo' => 'VEHICLE',
            'nome' => 'Hilux 2024',
            'valor_aquisicao' => 250000,
            'data_aquisicao' => '2026-01-15',
        ])), true);

        $this->assertTrue($ok['sucesso']);

        $asset = Asset::find($ok['ativo']['id']);
        $aquisicao = $asset->transactions()->where('type', 'BUY')->first();

        $this->assertNotNull($aquisicao);
        $this->assertEquals(250000.0, (float) $aquisicao->total_amount);
        $this->assertSame($this->tenant->getKey(), $asset->tenant_id);
    }

    public function test_tools_de_escrita_exigem_aprovacao_por_padrao(): void
    {
        foreach ([
            new CriarLancamento($this->tenant),
            new CadastrarAtivo($this->tenant),
            new RegistrarOperacao($this->tenant),
            new CriarRecorrencia($this->tenant),
            new CriarConta($this->tenant),
        ] as $tool) {
            $this->assertNotNull($tool->shouldRequestApproval(new Request), $tool::class.' deveria exigir aprovação');
        }
    }

    public function test_registrar_operacao_compra_cria_ativo_junto(): void
    {
        $conta = Account::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Nubank', 'kind' => 'bank', 'opening_balance' => 1000]);

        $result = json_decode(
            (new RegistrarOperacao($this->tenant))->handle(new Request([
                'operacao' => 'BUY',
                'ticker' => 'petr4',
                'quantidade' => 2,
                'preco_unitario' => 40,
                'data' => '2026-07-20',
                'instituicao' => 'Nubank',
                'account_id' => $conta->getKey(),
                'tipo_ativo' => 'STOCK',
            ])),
            true,
        );

        $this->assertTrue($result['sucesso']);
        $this->assertTrue($result['ativo_criado_junto']);
        $this->assertSame(2.0, (float) $result['posicao_atual']);

        $asset = Asset::where('ticker_or_code', 'PETR4')->where('tenant_id', $this->tenant->getKey())->first();
        $this->assertNotNull($asset);

        $compra = $asset->transactions()->where('type', 'BUY')->first();
        $this->assertSame('Credito', $compra->direction);
        $this->assertEquals(80.0, (float) $compra->total_amount);
        $this->assertSame($conta->getKey(), $compra->account_id);
    }

    public function test_registrar_operacao_exige_tipo_para_ativo_novo_e_valida_venda(): void
    {
        $tool = new RegistrarOperacao($this->tenant);

        // Compra de ativo inexistente sem tipo_ativo → pede o tipo.
        $semTipo = json_decode($tool->handle(new Request([
            'operacao' => 'BUY', 'ticker' => 'XPTO3', 'quantidade' => 1, 'preco_unitario' => 10, 'data' => '2026-07-20',
        ])), true);
        $this->assertArrayHasKey('erro', $semTipo);

        // Venda de ativo inexistente → erro.
        $vendaSemAtivo = json_decode($tool->handle(new Request([
            'operacao' => 'SELL', 'ticker' => 'XPTO3', 'quantidade' => 1, 'preco_unitario' => 10, 'data' => '2026-07-20',
        ])), true);
        $this->assertArrayHasKey('erro', $vendaSemAtivo);

        // Venda maior que a posição → erro com a posição atual.
        $this->seedProvento($this->tenant, 'PETR4', '2026-07-01', 1); // cria o ativo
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => Asset::where('ticker_or_code', 'PETR4')->where('tenant_id', $this->tenant->getKey())->value('id'),
            'type' => 'BUY', 'transaction_date' => '2026-07-01', 'quantity' => 5,
            'unit_price' => 10, 'total_amount' => 50, 'direction' => 'Credito', 'source' => 'manual',
        ]);

        $vendaDemais = json_decode($tool->handle(new Request([
            'operacao' => 'SELL', 'ticker' => 'PETR4', 'quantidade' => 10, 'preco_unitario' => 10, 'data' => '2026-07-20',
        ])), true);
        $this->assertArrayHasKey('erro', $vendaDemais);

        // Venda dentro da posição funciona.
        $vendaOk = json_decode($tool->handle(new Request([
            'operacao' => 'SELL', 'ticker' => 'PETR4', 'quantidade' => 3, 'preco_unitario' => 12, 'data' => '2026-07-20',
        ])), true);
        $this->assertTrue($vendaOk['sucesso']);
        $this->assertSame(2.0, (float) $vendaOk['posicao_atual']);
    }

    public function test_fluxo_de_caixa_filtra_por_empresa(): void
    {
        $padaria = Company::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Padaria do Lucas']);
        $outra = Company::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Oficina']);

        foreach ([[$padaria, 3500.00], [$outra, 900.00]] as [$empresa, $valor]) {
            Transaction::create([
                'tenant_id' => $this->tenant->getKey(),
                'company_id' => $empresa->getKey(),
                'type' => 'INCOME',
                'transaction_date' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(9)->toDateString(),
                'movement' => 'Faturamento',
                'quantity' => 1,
                'total_amount' => $valor,
                'direction' => 'Credito',
            ]);
        }

        $result = json_decode(
            (new ConsultarFluxoDeCaixa($this->tenant))->handle(new Request(['meses' => 4, 'empresa' => 'padaria'])),
            true,
        );

        $mes = now()->subMonthsNoOverflow(2)->format('Y-m');

        $this->assertSame('R$ 3.500,00', $result['por_mes'][$mes]['receitas']);

        $inexistente = json_decode(
            (new ConsultarFluxoDeCaixa($this->tenant))->handle(new Request(['empresa' => 'Mercearia'])),
            true,
        );

        $this->assertArrayHasKey('erro', $inexistente);
        $this->assertContains('Padaria do Lucas', $inexistente['empresas_cadastradas']);
    }

    public function test_criar_recorrencia_valida_e_cria(): void
    {
        $tool = new CriarRecorrencia($this->tenant);

        $invalida = json_decode($tool->handle(new Request([
            'tipo' => 'EXPENSE', 'descricao' => 'Netflix', 'valor_mensal' => 55.9, 'dia_do_mes' => 32,
        ])), true);
        $this->assertArrayHasKey('erro', $invalida);

        $ok = json_decode($tool->handle(new Request([
            'tipo' => 'EXPENSE',
            'descricao' => 'Assinatura Netflix',
            'valor_mensal' => 55.90,
            'dia_do_mes' => 10,
            'categoria' => 'Assinaturas & Software',
        ])), true);

        $this->assertTrue($ok['sucesso']);

        $recorrencia = RecurringTransaction::find($ok['recorrencia']['id']);

        $this->assertSame($this->tenant->getKey(), $recorrencia->tenant_id);
        $this->assertTrue($recorrencia->active);
        $this->assertSame(10, (int) $recorrencia->day_of_month);
    }

    public function test_criar_conta_valida_e_cria(): void
    {
        $tool = new CriarConta($this->tenant);

        $tipoInvalido = json_decode($tool->handle(new Request(['nome' => 'Nubank', 'tipo' => 'poupanca'])), true);
        $this->assertArrayHasKey('erro', $tipoInvalido);

        $ok = json_decode($tool->handle(new Request(['nome' => 'Nubank', 'tipo' => 'bank', 'saldo_inicial' => 1500])), true);
        $this->assertTrue($ok['sucesso']);

        $account = Account::find($ok['conta']['id']);
        $this->assertSame($this->tenant->getKey(), $account->tenant_id);
        $this->assertEquals(1500.0, (float) $account->opening_balance);

        $duplicada = json_decode($tool->handle(new Request(['nome' => 'Nubank'])), true);
        $this->assertArrayHasKey('erro', $duplicada);
    }

    public function test_criar_recorrencia_rejeita_conta_de_outro_tenant(): void
    {
        $contaAlheia = Account::create([
            'tenant_id' => $this->outroTenant->getKey(), 'name' => 'Alheia', 'kind' => 'bank', 'opening_balance' => 0,
        ]);

        $result = json_decode((new CriarRecorrencia($this->tenant))->handle(new Request([
            'tipo' => 'EXPENSE', 'descricao' => 'Netflix', 'valor_mensal' => 55.9, 'account_id' => $contaAlheia->getKey(),
        ])), true);

        $this->assertArrayHasKey('erro', $result);
        $this->assertSame(0, RecurringTransaction::count());
    }
}
