<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\CurrencyRate;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\CashFlow;
use App\Services\CurrencyConverter;
use App\Services\PortfolioEvolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-21');
        CurrencyConverter::flush();

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();

        // Dólar: 5,00 em janeiro, 5,50 hoje.
        CurrencyRate::create(['currency' => 'USD', 'date' => '2026-01-10', 'rate' => 5.00]);
        CurrencyRate::create(['currency' => 'USD', 'date' => '2026-07-20', 'rate' => 5.50]);
    }

    protected function tearDown(): void
    {
        CurrencyConverter::flush();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_converter_usa_a_ultima_cotacao_ate_a_data(): void
    {
        $converter = new CurrencyConverter;

        $this->assertEqualsWithDelta(5.00, $converter->rate('USD', '2026-03-01'), 1e-9);
        $this->assertEqualsWithDelta(5.50, $converter->rate('USD'), 1e-9);
        // Antes da série: primeira cotação conhecida.
        $this->assertEqualsWithDelta(5.00, $converter->rate('USD', '2025-01-01'), 1e-9);
        $this->assertEqualsWithDelta(1.0, $converter->rate('BRL', '2026-03-01'), 1e-9);
        $this->assertEqualsWithDelta(550.0, $converter->toBrl(100, 'USD'), 1e-9);
    }

    public function test_ativo_em_dolar_tem_custo_historico_e_valor_pelo_cambio_do_dia(): void
    {
        $artefato = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Artefato importado',
            'type' => 'COLLECTIBLE', 'currency' => 'USD',
        ]);

        // Comprado por US$ 1.000 quando o dólar era 5,00.
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $artefato->getKey(),
            'type' => 'BUY', 'transaction_date' => '2026-01-15', 'quantity' => 1,
            'total_amount' => 1000, 'direction' => 'Credito', 'source' => 'manual',
        ]);

        $artefato = $artefato->fresh()->load('transactions');

        // Custo: câmbio da data da compra. Valor: câmbio de hoje.
        $this->assertEqualsWithDelta(5000.0, $artefato->purchaseValue(), 1e-6);
        $this->assertEqualsWithDelta(5500.0, $artefato->currentValue(), 1e-6);
        // Rentabilidade = variação cambial (10%).
        $this->assertEqualsWithDelta(10.0, $artefato->percentChange(), 1e-6);
    }

    public function test_venda_de_ativo_em_dolar_realiza_o_ganho_cambial(): void
    {
        $asset = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Stock USD',
            'type' => 'STOCK', 'ticker_or_code' => 'USDX', 'currency' => 'USD',
        ]);

        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'type' => 'BUY', 'transaction_date' => '2026-01-15', 'quantity' => 10,
            'total_amount' => 1000, 'direction' => 'Credito', 'source' => 'manual',
        ]);
        // Vende pelos mesmos US$ 1.000, mas com dólar a 5,50.
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'type' => 'SELL', 'transaction_date' => '2026-07-21', 'quantity' => 10,
            'total_amount' => 1000, 'direction' => 'Debito', 'source' => 'manual',
        ]);

        $sales = $asset->fresh()->load('transactions')->realizedSalesForYear(2026);

        $this->assertCount(1, $sales);
        // Ganho cambial: 5.500 - 5.000 = R$ 500.
        $this->assertEqualsWithDelta(500.0, $sales[0]['gain'], 1e-6);
    }

    public function test_conta_em_dolar_entra_no_patrimonio_pelo_cambio_do_dia(): void
    {
        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Conta EUA', 'kind' => 'bank',
            'opening_balance' => 1000, 'currency' => 'USD',
        ]);

        $conta = $conta->fresh()->load('transactions');
        $this->assertEqualsWithDelta(1000.0, $conta->balance(), 1e-9); // nativo
        $this->assertEqualsWithDelta(5500.0, $conta->balanceInBrlAt(), 1e-6);

        $series = app(PortfolioEvolution::class)->monthlySeries($this->tenant);
        $this->assertEqualsWithDelta(5500.0, end($series['current']), 1e-6);
    }

    public function test_fluxo_de_caixa_converte_renda_em_dolar(): void
    {
        $software = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'SaaS gringo',
            'type' => 'SOFTWARE', 'currency' => 'USD',
        ]);

        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $software->getKey(),
            'type' => 'INCOME', 'transaction_date' => '2026-07-10', 'quantity' => 1,
            'total_amount' => 100, 'direction' => 'Credito', 'source' => 'manual',
        ]);

        $flow = app(CashFlow::class)->monthly($this->tenant, 3);

        // Convertido pelo câmbio DA DATA do recebimento (10/07 -> última
        // cotação conhecida era a de 10/01, R$ 5,00): US$ 100 = R$ 500.
        $this->assertEqualsWithDelta(500.0, end($flow['income']), 1e-6);
    }

    public function test_command_sincroniza_cotacoes_do_bcb(): void
    {
        CurrencyRate::query()->delete();
        CurrencyConverter::flush();

        Http::fake([
            'api.bcb.gov.br/*' => Http::response([
                ['data' => '18/07/2026', 'valor' => '5,0780'],
                ['data' => '21/07/2026', 'valor' => '5,1000'],
            ]),
        ]);

        $this->artisan('marketing:sync-currencies')->assertSuccessful();

        $this->assertSame(4, CurrencyRate::count()); // 2 datas x 2 moedas (fake responde igual)
        $this->assertEqualsWithDelta(5.10, (new CurrencyConverter)->rate('USD'), 1e-9);
    }
}
