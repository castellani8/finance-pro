<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\CashFlow;
use App\Services\PortfolioEvolution;
use App\Support\CompanyFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-21');

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_agrega_receitas_despesas_e_resultado_por_mes_com_filtro_por_empresa(): void
    {
        $empresa = Company::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Fazenda X']);

        $trator = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'company_id' => $empresa->getKey(),
            'name' => 'Trator', 'type' => 'MACHINERY', 'currency' => 'BRL',
        ]);

        // Renda do trator (ativo da empresa) + despesa avulsa da empresa + receita sem empresa.
        $this->tx(['asset_id' => $trator->getKey(), 'type' => 'INCOME', 'transaction_date' => '2026-07-05', 'total_amount' => 3000, 'direction' => 'Credito']);
        $this->tx(['company_id' => $empresa->getKey(), 'type' => 'EXPENSE', 'transaction_date' => '2026-07-10', 'total_amount' => 100, 'direction' => 'Debito']);
        $this->tx(['type' => 'INCOME', 'transaction_date' => '2026-07-15', 'total_amount' => 900, 'direction' => 'Credito']);

        $all = app(CashFlow::class)->monthly($this->tenant, 12);
        $this->assertEqualsWithDelta(3900.0, end($all['income']), 1e-9);
        $this->assertEqualsWithDelta(100.0, end($all['expenses']), 1e-9);
        $this->assertEqualsWithDelta(3800.0, end($all['result']), 1e-9);

        // Filtro da empresa: aluguel do trator (via ativo) + assinatura (direta); a receita avulsa fica fora.
        $daEmpresa = app(CashFlow::class)->monthly($this->tenant, 12, $empresa->getKey());
        $this->assertEqualsWithDelta(3000.0, end($daEmpresa['income']), 1e-9);
        $this->assertEqualsWithDelta(100.0, end($daEmpresa['expenses']), 1e-9);
        $this->assertEqualsWithDelta(2900.0, end($daEmpresa['result']), 1e-9);

        // Resultado 12m da empresa bate com o fluxo filtrado.
        $this->assertEqualsWithDelta(2900.0, $empresa->netResultLastTwelveMonths(), 1e-9);
    }

    public function test_evolucao_do_patrimonio_filtrada_por_empresa_considera_so_os_ativos_dela(): void
    {
        $empresa = Company::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Fazenda X']);

        $daEmpresa = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'company_id' => $empresa->getKey(),
            'name' => 'Trator', 'type' => 'MACHINERY', 'currency' => 'BRL',
        ]);
        $semEmpresa = Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'Galpão pessoal', 'type' => 'REAL_ESTATE', 'currency' => 'BRL',
        ]);

        $this->tx(['asset_id' => $daEmpresa->getKey(), 'type' => 'BUY', 'transaction_date' => '2026-05-01', 'total_amount' => 100000, 'direction' => 'Credito']);
        $this->tx(['asset_id' => $semEmpresa->getKey(), 'type' => 'BUY', 'transaction_date' => '2026-05-01', 'total_amount' => 200000, 'direction' => 'Credito']);

        $tudo = app(PortfolioEvolution::class)->monthlySeries($this->tenant);
        $soEmpresa = app(PortfolioEvolution::class)->monthlySeries($this->tenant, null, $empresa->getKey());
        $semEmpresa = app(PortfolioEvolution::class)->monthlySeries($this->tenant, null, CompanyFilter::NONE);

        $this->assertEqualsWithDelta(300000.0, end($tudo['current']), 1e-6);
        $this->assertEqualsWithDelta(100000.0, end($soEmpresa['current']), 1e-6);
        // "Sem empresa" pega só o galpão pessoal.
        $this->assertEqualsWithDelta(200000.0, end($semEmpresa['current']), 1e-6);
    }

    public function test_benchmarks_nao_aparecem_quando_o_recorte_so_tem_bens_fisicos(): void
    {
        $empresa = Company::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Fazenda X']);

        $carro = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'company_id' => $empresa->getKey(),
            'name' => 'VW Fox', 'type' => 'VEHICLE', 'currency' => 'BRL',
        ]);
        $acao = Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'Ação', 'type' => 'STOCK', 'ticker_or_code' => 'TEST3', 'currency' => 'BRL',
        ]);

        $this->tx(['asset_id' => $carro->getKey(), 'type' => 'BUY', 'transaction_date' => '2026-05-01', 'total_amount' => 43000, 'direction' => 'Credito']);
        $this->tx(['asset_id' => $acao->getKey(), 'type' => 'BUY', 'transaction_date' => '2026-05-01', 'total_amount' => 10000, 'direction' => 'Credito']);

        // Filtrado pela empresa (só o carro): sem linhas de CDI/IBOV.
        $daEmpresa = app(PortfolioEvolution::class)->monthlySeries($this->tenant, null, $empresa->getKey());
        $this->assertSame([], $daEmpresa['cdi']);
        $this->assertSame([], $daEmpresa['ibov']);

        // Carteira completa (tem ação): benchmarks presentes.
        $tudo = app(PortfolioEvolution::class)->monthlySeries($this->tenant);
        $this->assertNotSame([], $tudo['cdi']);
    }

    public function test_fluxo_de_caixa_sem_empresa_exclui_lancamentos_e_ativos_de_empresas(): void
    {
        $empresa = Company::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Fazenda X']);
        $daEmpresa = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'company_id' => $empresa->getKey(),
            'name' => 'Trator', 'type' => 'MACHINERY', 'currency' => 'BRL',
        ]);

        $this->tx(['asset_id' => $daEmpresa->getKey(), 'type' => 'INCOME', 'transaction_date' => '2026-07-05', 'total_amount' => 3000, 'direction' => 'Credito']);
        $this->tx(['company_id' => $empresa->getKey(), 'type' => 'EXPENSE', 'transaction_date' => '2026-07-10', 'total_amount' => 100, 'direction' => 'Debito']);
        $this->tx(['type' => 'INCOME', 'transaction_date' => '2026-07-15', 'total_amount' => 900, 'direction' => 'Credito']);

        $pessoal = app(CashFlow::class)->monthly($this->tenant, 12, CompanyFilter::NONE);

        // Só a receita avulsa sem empresa entra no recorte pessoal.
        $this->assertEqualsWithDelta(900.0, end($pessoal['income']), 1e-9);
        $this->assertEqualsWithDelta(0.0, end($pessoal['expenses']), 1e-9);
    }

    public function test_lancamento_avulso_nao_contamina_patrimonio_nem_ativos(): void
    {
        $galpao = Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'Galpão', 'type' => 'REAL_ESTATE', 'currency' => 'BRL',
        ]);
        $this->tx(['asset_id' => $galpao->getKey(), 'type' => 'BUY', 'transaction_date' => '2026-01-01', 'total_amount' => 200000, 'direction' => 'Credito']);

        $before = app(PortfolioEvolution::class)->monthlySeries($this->tenant);

        $this->tx(['type' => 'EXPENSE', 'transaction_date' => '2026-06-01', 'total_amount' => 5000, 'direction' => 'Debito']);
        $this->tx(['type' => 'INCOME', 'transaction_date' => '2026-06-02', 'total_amount' => 9000, 'direction' => 'Credito']);

        $after = app(PortfolioEvolution::class)->monthlySeries($this->tenant);

        $this->assertSame($before['current'], $after['current']);
        $this->assertSame($before['invested'], $after['invested']);

        $galpao = $galpao->fresh()->load('transactions');
        $this->assertEqualsWithDelta(200000.0, $galpao->currentValue(), 1e-6);
        $this->assertEqualsWithDelta(0.0, $galpao->dividendsReceived(), 1e-9);
    }

    public function test_resultado_do_ativo_confronta_renda_e_despesas(): void
    {
        $trator = Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'Trator', 'type' => 'MACHINERY', 'currency' => 'BRL',
        ]);

        $this->tx(['asset_id' => $trator->getKey(), 'type' => 'BUY', 'transaction_date' => '2025-01-01', 'total_amount' => 100000, 'direction' => 'Credito']);
        $this->tx(['asset_id' => $trator->getKey(), 'type' => 'INCOME', 'transaction_date' => '2026-05-05', 'total_amount' => 36000, 'direction' => 'Credito']);
        $this->tx(['asset_id' => $trator->getKey(), 'type' => 'EXPENSE', 'transaction_date' => '2026-06-01', 'total_amount' => 8000, 'direction' => 'Debito']);

        $trator = $trator->fresh()->load('transactions');

        $this->assertEqualsWithDelta(28000.0, $trator->netResult(), 1e-9);
        $this->assertEqualsWithDelta(28000.0, $trator->netResultLastTwelveMonths(), 1e-9);
        // O valor do bem não é afetado pela renda.
        $this->assertEqualsWithDelta(100000.0, $trator->currentValue(), 1e-6);
    }

    private function tx(array $attributes): Transaction
    {
        return Transaction::create(array_merge([
            'tenant_id' => $this->tenant->getKey(),
            'quantity' => 1,
            'source' => 'manual',
        ], $attributes));
    }
}
