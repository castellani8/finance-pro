<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\IrReport;
use App\Services\PortfolioEvolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhysicalAssetValuationTest extends TestCase
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

    public function test_benfeitoria_soma_valor_e_custo_e_despesa_soma_so_o_custo(): void
    {
        $trator = $this->makeAsset('MACHINERY');

        $this->tx($trator, 'BUY', '2026-01-01', 100_000);
        $this->tx($trator, 'IMPROVEMENT', '2026-02-01', 20_000);
        $this->tx($trator, 'EXPENSE', '2026-03-01', 5_000);

        $trator = $trator->fresh()->load('transactions');

        // Valor de compra é só a aquisição — revisão/benfeitoria não mudam o preço pago.
        $this->assertEqualsWithDelta(100_000.0, $trator->acquisitionValue(), 1e-6);
        // Investido: aquisição + benfeitoria + despesa.
        $this->assertEqualsWithDelta(125_000.0, $trator->purchaseValue(), 1e-6);
        // Valor: aquisição + benfeitoria (despesa não valoriza o bem).
        $this->assertEqualsWithDelta(120_000.0, $trator->currentValue(), 1e-6);
        // Lançamentos sem quantidade não mexem na posição.
        $this->assertEqualsWithDelta(1.0, $trator->positionQuantity(), 1e-9);
    }

    public function test_caso_fox_compra_43k_com_revisao_de_4k_mantem_valor_de_compra(): void
    {
        $fox = $this->makeAsset('VEHICLE');

        $this->tx($fox, 'BUY', '2026-05-01', 43_000);
        $this->tx($fox, 'EXPENSE', '2026-06-01', 4_000);

        $fox = $fox->fresh()->load('transactions');

        $this->assertEqualsWithDelta(43_000.0, $fox->acquisitionValue(), 1e-6);
        $this->assertEqualsWithDelta(47_000.0, $fox->purchaseValue(), 1e-6);
        $this->assertEqualsWithDelta(43_000.0, $fox->currentValue(), 1e-6);
    }

    public function test_depreciacao_linear_prorata_desde_a_aquisicao(): void
    {
        $carro = $this->makeAsset('VEHICLE', ['depreciation_rate' => 20]);

        // Comprado exatamente 1 ano atrás: 20% a.a. -> vale 80% do valor.
        $this->tx($carro, 'BUY', '2025-07-21', 50_000);

        $carro = $carro->fresh()->load('transactions');

        $this->assertEqualsWithDelta(40_000.0, $carro->currentValue(), 50.0);
        // O investido não deprecia.
        $this->assertEqualsWithDelta(50_000.0, $carro->purchaseValue(), 1e-6);
    }

    public function test_reavaliacao_redefine_a_base_e_zera_o_relogio_da_depreciacao(): void
    {
        $casa = $this->makeAsset('REAL_ESTATE', ['depreciation_rate' => 4]);

        $this->tx($casa, 'BUY', '2024-07-21', 300_000);
        // Reavaliada hoje por 500 mil: valor atual = 500 mil (sem depreciação ainda).
        $this->tx($casa, 'REVALUATION', '2026-07-21', 500_000);

        $casa = $casa->fresh()->load('transactions');

        $this->assertEqualsWithDelta(500_000.0, $casa->currentValue(), 1e-6);
        // Antes da reavaliação (1 ano após compra): 300k - 4% = 288k.
        $this->assertEqualsWithDelta(288_000.0, $casa->valueAt('2025-07-21', null), 100.0);
        // Benfeitoria depois da reavaliação soma à nova base.
        $this->tx($casa, 'IMPROVEMENT', '2026-07-21', 50_000);
        $casa = $casa->fresh()->load('transactions');
        $this->assertEqualsWithDelta(550_000.0, $casa->currentValue(), 1e-6);
    }

    public function test_software_construido_so_com_benfeitorias_tem_valor_e_aparece_na_evolucao(): void
    {
        $software = $this->makeAsset('SOFTWARE');

        $this->tx($software, 'BUY', '2026-05-01', 0); // aquisição simbólica (criado do zero)
        $this->tx($software, 'IMPROVEMENT', '2026-06-01', 3_000, 'Horas trabalhadas');

        $software = $software->fresh()->load('transactions');

        $this->assertEqualsWithDelta(3_000.0, $software->currentValue(), 1e-6);
        $this->assertEqualsWithDelta(3_000.0, $software->purchaseValue(), 1e-6);

        $series = app(PortfolioEvolution::class)->monthlySeries($this->tenant);
        $this->assertEqualsWithDelta(3_000.0, end($series['current']), 1e-6);
    }

    public function test_venda_zera_o_bem_e_renda_de_aluguel_conta_como_provento(): void
    {
        $galpao = $this->makeAsset('REAL_ESTATE');

        $this->tx($galpao, 'BUY', '2026-01-01', 200_000);
        $this->tx($galpao, 'INCOME', '2026-02-01', 3_500);
        $this->tx($galpao, 'SELL', '2026-06-01', 250_000, direction: 'Debito');

        $galpao = $galpao->fresh()->load('transactions');

        $this->assertEqualsWithDelta(0.0, $galpao->positionQuantity(), 1e-9);
        $this->assertEqualsWithDelta(0.0, $galpao->currentValue(), 1e-6);
        $this->assertEqualsWithDelta(3_500.0, $galpao->dividendsReceived(), 1e-6);
        $this->assertSame(0, Asset::wherePositionPositive()->whereKey($galpao->getKey())->count());
    }

    public function test_bem_fisico_entra_no_relatorio_de_ir(): void
    {
        $trator = $this->makeAsset('MACHINERY');
        $this->tx($trator, 'BUY', '2025-03-01', 100_000);
        $this->tx($trator, 'EXPENSE', '2025-09-01', 5_000);

        $report = app(IrReport::class)->build($this->tenant, 2025);

        $this->assertCount(1, $report['bens']);
        $this->assertEqualsWithDelta(105_000.0, $report['bens'][0]['custo_atual'], 1e-6);
        $this->assertSame('02 / 99 — Máquinas e equipamentos', $report['bens'][0]['grupo_codigo']);
        $this->assertStringContainsString('benfeitorias e despesas', $report['bens'][0]['discriminacao']);
    }

    private function makeAsset(string $type, array $metadata = []): Asset
    {
        return Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => "Bem {$type}",
            'type' => $type,
            'currency' => 'BRL',
            'metadata' => $metadata,
        ]);
    }

    private function tx(Asset $asset, string $type, string $date, float $total, ?string $notes = null, string $direction = 'Credito'): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'type' => $type,
            'transaction_date' => $date,
            'quantity' => 1,
            'total_amount' => $total,
            'direction' => $type === 'SELL' || $type === 'EXPENSE' ? 'Debito' : $direction,
            'notes' => $notes,
            'source' => 'manual',
        ]);
    }
}
