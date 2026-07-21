<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPriceHistory;
use App\Models\PortfolioSnapshot;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\PortfolioEvolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortfolioEvolutionTest extends TestCase
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

    public function test_estorno_de_provento_em_debito_subtrai_do_total(): void
    {
        $asset = $this->makeAsset('CDBX', 'FIXED_INCOME');

        $this->makeTransaction($asset, 'INTEREST', '2026-05-10', 1, 100.0, 'Credito');
        $this->makeTransaction($asset, 'INTEREST', '2026-06-10', 1, 30.0, 'Debito');

        $this->assertEqualsWithDelta(70.0, $asset->fresh()->load('transactions')->dividendsReceived(), 1e-9);
    }

    public function test_posicao_e_custo_respeitam_a_data_de_corte(): void
    {
        $asset = $this->makeAsset('TEST3', 'STOCK');

        $this->makeTransaction($asset, 'BUY', '2026-01-10', 100, 1000.0, 'Credito');
        $this->makeTransaction($asset, 'SELL', '2026-03-10', 40, 480.0, 'Debito');

        $asset = $asset->fresh()->load('transactions');

        $this->assertEqualsWithDelta(100.0, $asset->positionQuantity('2026-02-28'), 1e-9);
        $this->assertEqualsWithDelta(60.0, $asset->positionQuantity('2026-03-31'), 1e-9);
        $this->assertEqualsWithDelta(1000.0, $asset->purchaseValue('2026-02-28'), 1e-6);
        $this->assertEqualsWithDelta(600.0, $asset->purchaseValue('2026-03-31'), 1e-6);
    }

    public function test_serie_mensal_usa_cotacao_quando_existe_e_custo_como_fallback(): void
    {
        $asset = $this->makeAsset('TEST3', 'STOCK');
        $this->makeTransaction($asset, 'BUY', '2026-05-10', 100, 1000.0, 'Credito');

        AssetPriceHistory::create([
            'ticker' => 'TEST3',
            'date' => '2026-06-25',
            'price_open' => 12,
            'price_close' => 12.5,
            'price_high' => 13,
            'price_low' => 11,
            'source' => 'test',
        ]);

        $series = app(PortfolioEvolution::class)->monthlySeries($this->tenant);

        // Pontos: 31/05 (sem cotação -> custo), 30/06 (cotação 12,50) e hoje (21/07).
        $this->assertSame(3, count($series['labels']));
        $this->assertEqualsWithDelta(1000.0, $series['invested'][0], 1e-6);
        $this->assertEqualsWithDelta(1000.0, $series['current'][0], 1e-6);
        $this->assertEqualsWithDelta(1250.0, $series['current'][1], 1e-6);
        $this->assertEqualsWithDelta(1250.0, $series['current'][2], 1e-6);
    }

    public function test_serie_diaria_materializa_snapshots_e_reusa_os_dias_ja_gravados(): void
    {
        $asset = $this->makeAsset('TEST3', 'STOCK');
        $this->makeTransaction($asset, 'BUY', '2026-07-10', 100, 1000.0, 'Credito');

        $series = app(PortfolioEvolution::class)->dailySeries($this->tenant, 5);

        $this->assertSame(5, count($series['labels']));
        // Todos os dias do recorte ficam fotografados na tabela.
        $this->assertSame(5, PortfolioSnapshot::where('tenant_id', $this->tenant->getKey())->count());

        // Segunda chamada reusa os snapshots (só "hoje" é recalculado).
        $again = app(PortfolioEvolution::class)->dailySeries($this->tenant, 5);
        $this->assertSame($series['current'], $again['current']);
        $this->assertSame(5, PortfolioSnapshot::where('tenant_id', $this->tenant->getKey())->count());
    }

    public function test_dy_e_yield_on_cost_usam_proventos_dos_ultimos_12_meses(): void
    {
        $asset = $this->makeAsset('TEST3', 'STOCK');
        $this->makeTransaction($asset, 'BUY', '2024-01-10', 100, 1000.0, 'Credito');
        $this->makeTransaction($asset, 'DIVIDEND', '2026-05-10', 100, 50.0, 'Credito');
        // Provento antigo (fora da janela de 12 meses) não conta.
        $this->makeTransaction($asset, 'DIVIDEND', '2024-05-10', 100, 999.0, 'Credito');

        $asset = $asset->fresh()->load('transactions');

        $this->assertEqualsWithDelta(50.0, $asset->incomeLastTwelveMonths(), 1e-9);
        // Sem cotação, valor atual = custo, então DY = YoC = 50/1000 = 5%.
        $this->assertEqualsWithDelta(5.0, $asset->dividendYield(), 1e-9);
        $this->assertEqualsWithDelta(5.0, $asset->yieldOnCost(), 1e-9);
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

    private function makeTransaction(Asset $asset, string $type, string $date, float $quantity, float $total, string $direction): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'type' => $type,
            'transaction_date' => $date,
            'quantity' => $quantity,
            'unit_price' => $quantity > 0 ? $total / $quantity : null,
            'total_amount' => $total,
            'direction' => $direction,
            'source' => 'manual',
        ]);
    }
}
