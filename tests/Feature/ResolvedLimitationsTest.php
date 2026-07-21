<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\MarketingIndexSeries;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\IndexAccumulator;
use App\Services\IrReport;
use App\Services\PortfolioEvolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cobre os pontos que eram "limitações conhecidas" e foram resolvidos:
 * custo pelo pool (grupamento/bonificação), depreciação por componente,
 * pró-rata de índices mensais, contas com saldo no patrimônio e ganho de
 * capital no IR.
 */
class ResolvedLimitationsTest extends TestCase
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

    public function test_custo_sobrevive_ao_grupamento_pelo_metodo_do_pool(): void
    {
        $asset = $this->makeAsset('TEST3', 'STOCK');

        // 111 ações por R$ 1.110; grupamento 20:1 -> 5,55 ações, MESMO custo.
        $this->tx($asset, 'BUY', '2026-01-10', 111, 1110.0, 'Credito');
        $this->tx($asset, 'GROUPING', '2026-03-01', 5.55, 0.0, 'Credito');

        $asset = $asset->fresh()->load('transactions');

        $this->assertEqualsWithDelta(1110.0, $asset->purchaseValue(), 1e-6);
        $this->assertEqualsWithDelta(200.0, $asset->averageBuyPrice(), 1e-6);

        // Vender tudo remove todo o custo e registra o ganho realizado.
        $this->tx($asset, 'SELL', '2026-04-01', 5.55, 1500.0, 'Debito');
        $asset = $asset->fresh()->load('transactions');

        $this->assertEqualsWithDelta(0.0, $asset->purchaseValue(), 1e-6);
        $sales = $asset->realizedSalesForYear(2026);
        $this->assertCount(1, $sales);
        $this->assertEqualsWithDelta(390.0, $sales[0]['gain'], 1e-6); // 1500 - 1110
    }

    public function test_bonificacao_com_custo_informado_entra_no_pool(): void
    {
        $asset = $this->makeAsset('TEST3', 'STOCK');

        $this->tx($asset, 'BUY', '2026-01-10', 100, 1000.0, 'Credito');
        $this->tx($asset, 'BONUS', '2026-02-10', 10, 500.0, 'Credito'); // custo informado pela empresa

        $asset = $asset->fresh()->load('transactions');

        $this->assertEqualsWithDelta(1500.0, $asset->purchaseValue(), 1e-6);
        $this->assertEqualsWithDelta(1500.0 / 110.0, $asset->averageBuyPrice(), 1e-6);
    }

    public function test_depreciacao_tem_relogio_proprio_por_benfeitoria(): void
    {
        $trator = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Trator', 'type' => 'MACHINERY',
            'currency' => 'BRL', 'metadata' => ['depreciation_rate' => 10],
        ]);

        $this->tx($trator, 'BUY', '2025-07-21', 1, 100_000.0, 'Credito');       // 1 ano: -10%
        $this->tx($trator, 'IMPROVEMENT', '2026-01-21', 1, 20_000.0, 'Credito'); // ~6 meses: -5%

        $trator = $trator->fresh()->load('transactions');

        // 100k x 0,90 + 20k x ~0,95 = ~109k (antes seria 120k x 0,90 = 108k).
        $this->assertEqualsWithDelta(109_000.0, $trator->currentValue(), 100.0);
    }

    public function test_indice_mensal_entra_pro_rata_no_meio_do_mes(): void
    {
        foreach ([['2026-05-01', 0.5], ['2026-06-01', 1.0]] as [$date, $rate]) {
            MarketingIndexSeries::forceCreate([
                'index_code' => 'IPCA', 'date' => $date, 'daily_factor' => $rate, 'annual_rate' => null,
            ]);
        }

        $accumulator = new IndexAccumulator;

        // De 30/04 a 15/06: maio inteiro (1,005) + fração de junho (14/30 de 1%).
        $factor = $accumulator->factorBetween('IPCA', '2026-04-30', '2026-06-15');
        $expected = 1.005 * (1 + 0.01 * (14 / 30));

        $this->assertEqualsWithDelta($expected, $factor, 1e-6);
    }

    public function test_conta_tem_saldo_e_entra_no_patrimonio(): void
    {
        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Nubank', 'kind' => 'bank',
            'opening_balance' => 1000,
        ]);

        // Despesa avulsa paga pela conta: sai do saldo (e do patrimônio).
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'account_id' => $conta->getKey(),
            'type' => 'EXPENSE', 'transaction_date' => '2026-07-01', 'quantity' => 1,
            'total_amount' => 200, 'direction' => 'Debito', 'source' => 'manual',
        ]);
        // Renda recebida na conta: entra.
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'account_id' => $conta->getKey(),
            'type' => 'INCOME', 'transaction_date' => '2026-07-02', 'quantity' => 1,
            'total_amount' => 50, 'direction' => 'Credito', 'source' => 'manual',
        ]);

        $conta = $conta->fresh()->load('transactions');
        $this->assertEqualsWithDelta(850.0, $conta->balance(), 1e-9);
        $this->assertEqualsWithDelta(1000.0, $conta->balanceAt('2026-06-30'), 1e-9);

        $series = app(PortfolioEvolution::class)->monthlySeries($this->tenant);
        $this->assertEqualsWithDelta(850.0, end($series['current']), 1e-6);
    }

    public function test_comprar_ativo_pela_conta_tira_dinheiro_do_saldo(): void
    {
        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Corretora', 'kind' => 'broker',
            'opening_balance' => 5000,
        ]);
        $asset = $this->makeAsset('TEST3', 'STOCK');

        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'account_id' => $conta->getKey(), 'type' => 'BUY', 'transaction_date' => '2026-07-01',
            'quantity' => 100, 'total_amount' => 3000, 'direction' => 'Credito', 'source' => 'manual',
        ]);

        // Patrimônio não dobra: saldo cai 3.000 e o ativo vale 3.000.
        $this->assertEqualsWithDelta(2000.0, $conta->fresh()->load('transactions')->balance(), 1e-9);
        $series = app(PortfolioEvolution::class)->monthlySeries($this->tenant);
        $this->assertEqualsWithDelta(5000.0, end($series['current']), 1e-6);
    }

    public function test_ir_apura_ganho_de_capital_com_isencao_e_darf(): void
    {
        $isenta = $this->makeAsset('ISEN3', 'STOCK');
        $this->tx($isenta, 'BUY', '2025-01-10', 100, 10_000.0, 'Credito');
        $this->tx($isenta, 'SELL', '2025-03-10', 100, 15_000.0, 'Debito'); // vendas 15k <= 20k -> isento

        $tributada = $this->makeAsset('TRIB3', 'STOCK');
        $this->tx($tributada, 'BUY', '2025-01-10', 100, 30_000.0, 'Credito');
        $this->tx($tributada, 'SELL', '2025-05-10', 100, 42_000.0, 'Debito'); // vendas 42k > 20k -> 15% de 12k

        $report = app(IrReport::class)->build($this->tenant, 2025);

        $marco = collect($report['ganhos'])->firstWhere('mes', 3);
        $this->assertTrue($marco['acoes']['isento']);
        $this->assertEqualsWithDelta(0.0, $marco['acoes']['darf'], 1e-6);

        $maio = collect($report['ganhos'])->firstWhere('mes', 5);
        $this->assertFalse($maio['acoes']['isento']);
        $this->assertEqualsWithDelta(1800.0, $maio['acoes']['darf'], 1e-6); // 15% de 12.000

        $this->assertEqualsWithDelta(1800.0, $report['totais']['darf'], 1e-6);
    }

    public function test_conta_com_saldo_aparece_nos_bens_do_ir(): void
    {
        Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Nubank', 'kind' => 'bank',
            'opening_balance' => 2500,
        ]);

        $report = app(IrReport::class)->build($this->tenant, 2025);

        $conta = collect($report['bens'])->firstWhere('tipo', 'ACCOUNT');
        $this->assertNotNull($conta);
        $this->assertSame('06 / 01 — Depósito em conta', $conta['grupo_codigo']);
        $this->assertEqualsWithDelta(2500.0, $conta['custo_atual'], 1e-6);
    }

    private function makeAsset(string $ticker, string $type): Asset
    {
        return Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => "Ativo {$ticker}",
            'type' => $type,
            'ticker_or_code' => $ticker,
            'currency' => 'BRL',
        ]);
    }

    private function tx(Asset $asset, string $type, string $date, float $qty, float $total, string $direction): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'type' => $type,
            'transaction_date' => $date,
            'quantity' => $qty,
            'total_amount' => $total,
            'direction' => $direction,
            'source' => 'manual',
        ]);
    }
}
