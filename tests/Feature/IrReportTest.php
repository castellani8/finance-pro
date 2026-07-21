<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\IrReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class IrReportTest extends TestCase
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

    public function test_bens_e_direitos_proventos_e_vendas_do_ano(): void
    {
        $asset = Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'EMPRESA TESTE S.A.',
            'type' => 'STOCK',
            'ticker_or_code' => 'TEST3',
            'currency' => 'BRL',
        ]);

        $tx = fn (string $type, string $date, float $qty, float $total, string $direction) => Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'type' => $type,
            'transaction_date' => $date,
            'quantity' => $qty,
            'total_amount' => $total,
            'direction' => $direction,
            'source' => 'manual',
        ]);

        $tx('BUY', '2024-06-10', 100, 1000.0, 'Credito');   // custo 31/12/2024 = 1000
        $tx('SELL', '2025-08-10', 40, 480.0, 'Debito');     // custo 31/12/2025 = 600
        $tx('DIVIDEND', '2025-09-01', 60, 50.0, 'Credito');
        $tx('JCP', '2025-10-01', 60, 30.0, 'Credito');
        $tx('DIVIDEND', '2026-02-01', 60, 99.0, 'Credito'); // fora do ano-base

        $report = app(IrReport::class)->build($this->tenant, 2025);

        $this->assertCount(1, $report['bens']);
        $bem = $report['bens'][0];
        $this->assertSame('TEST3', $bem['ticker']);
        $this->assertEqualsWithDelta(60.0, $bem['quantidade'], 1e-9);
        $this->assertEqualsWithDelta(1000.0, $bem['custo_anterior'], 1e-6);
        $this->assertEqualsWithDelta(600.0, $bem['custo_atual'], 1e-6);
        $this->assertStringContainsString('03 / 01', $bem['grupo_codigo']);
        $this->assertStringContainsString('60 ações', $bem['discriminacao']);

        $this->assertCount(1, $report['proventos']);
        $this->assertEqualsWithDelta(50.0, $report['proventos'][0]['isentos'], 1e-9);
        $this->assertEqualsWithDelta(30.0, $report['proventos'][0]['exclusivos'], 1e-9);

        $this->assertCount(1, $report['vendas']);
        $this->assertSame(8, $report['vendas'][0]['mes']);
        $this->assertEqualsWithDelta(480.0, $report['vendas'][0]['acoes'], 1e-9);
        $this->assertFalse($report['vendas'][0]['acoes_acima_isencao']);
    }

    public function test_ativo_zerado_no_ano_aparece_com_situacao_final_zero(): void
    {
        $asset = Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'ZERADA S.A.',
            'type' => 'STOCK',
            'ticker_or_code' => 'ZERA3',
            'currency' => 'BRL',
        ]);

        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'type' => 'BUY', 'transaction_date' => '2024-03-01', 'quantity' => 10,
            'total_amount' => 100.0, 'direction' => 'Credito', 'source' => 'manual',
        ]);
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'type' => 'SELL', 'transaction_date' => '2025-05-01', 'quantity' => 10,
            'total_amount' => 120.0, 'direction' => 'Debito', 'source' => 'manual',
        ]);

        $report = app(IrReport::class)->build($this->tenant, 2025);

        // Tinha custo em 31/12/2024, zerou em 2025: precisa aparecer com situação final 0.
        $this->assertCount(1, $report['bens']);
        $this->assertEqualsWithDelta(100.0, $report['bens'][0]['custo_anterior'], 1e-6);
        $this->assertEqualsWithDelta(0.0, $report['bens'][0]['custo_atual'], 1e-6);
    }
}
