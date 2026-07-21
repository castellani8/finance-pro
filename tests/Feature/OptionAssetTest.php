<?php

namespace Tests\Feature;

use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\IrReport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ciclo de vida de opções: lançamento (posição vendida), recompra, exercício,
 * vencimento sem valor e a apuração de IR específica (15% sem isenção).
 */
class OptionAssetTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();

        $user = User::create(['name' => 'Lucas', 'email' => 'lucas@teste.dev', 'password' => 'secret-123']);
        $this->tenant->users()->attach($user);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->tenant);
    }

    public function test_lancamento_com_recompra_realiza_o_premio_menos_o_custo_na_data_da_recompra(): void
    {
        $opcao = $this->makeOption();
        $this->addTransaction($opcao, 'SELL', '2026-02-05', 100, 150.0, 'Debito');
        $this->addTransaction($opcao, 'BUY', '2026-03-10', 100, 50.0, 'Credito');

        $opcao = $opcao->fresh()->load('transactions');

        $this->assertEqualsWithDelta(0.0, $opcao->positionQuantity(), 1e-9);
        $this->assertEqualsWithDelta(0.0, $opcao->openShortPremium(), 1e-9);

        $vendas = $opcao->realizedSalesForYear(2026);
        $this->assertCount(1, $vendas);
        $this->assertSame('2026-03-10', $vendas[0]['date']);
        $this->assertEqualsWithDelta(150.0, $vendas[0]['proceeds'], 1e-9);
        $this->assertEqualsWithDelta(50.0, $vendas[0]['cost'], 1e-9);
        $this->assertEqualsWithDelta(100.0, $vendas[0]['gain'], 1e-9);
    }

    public function test_lancamento_vencendo_po_realiza_o_premio_como_ganho(): void
    {
        $opcao = $this->makeOption();
        $this->addTransaction($opcao, 'SELL', '2026-02-05', 100, 200.0, 'Debito');
        $this->addTransaction($opcao, 'EXPIRE', '2026-06-19', 100, 0.0, 'Credito');

        $opcao = $opcao->fresh()->load('transactions');
        $vendas = $opcao->realizedSalesForYear(2026);

        $this->assertEqualsWithDelta(0.0, $opcao->positionQuantity(), 1e-9);
        $this->assertCount(1, $vendas);
        $this->assertEqualsWithDelta(200.0, $vendas[0]['gain'], 1e-9);
    }

    public function test_compra_vencendo_po_realiza_o_premio_como_perda(): void
    {
        $opcao = $this->makeOption();
        $this->addTransaction($opcao, 'BUY', '2026-02-05', 100, 300.0, 'Credito');
        $this->addTransaction($opcao, 'EXPIRE', '2026-06-19', 100, 0.0, 'Debito');

        $opcao = $opcao->fresh()->load('transactions');
        $vendas = $opcao->realizedSalesForYear(2026);

        $this->assertEqualsWithDelta(0.0, $opcao->positionQuantity(), 1e-9);
        $this->assertEqualsWithDelta(0.0, $opcao->purchaseValue(), 1e-9);
        $this->assertCount(1, $vendas);
        $this->assertEqualsWithDelta(-300.0, $vendas[0]['gain'], 1e-9);
    }

    public function test_posicao_vendida_aparece_como_passivo_e_entra_no_scope_de_posicao_aberta(): void
    {
        $opcao = $this->makeOption();
        $this->addTransaction($opcao, 'SELL', '2026-02-05', 100, 150.0, 'Debito');

        $opcao = $opcao->fresh()->load('transactions');

        $this->assertEqualsWithDelta(-100.0, $opcao->positionQuantity(), 1e-9);
        $this->assertEqualsWithDelta(150.0, $opcao->openShortPremium(), 1e-9);
        // Sem cotação, o passivo é o prêmio em aberto; o prêmio médio é 1,50.
        $this->assertEqualsWithDelta(-150.0, $opcao->currentValue(), 1e-9);
        $this->assertEqualsWithDelta(1.5, $opcao->averageBuyPrice(), 1e-9);

        // O observer materializa a posição negativa e o novo scope a inclui
        // (o antigo, usado por gráficos de alocação, continua excluindo).
        $this->assertEqualsWithDelta(-100.0, (float) $opcao->position_quantity, 1e-6);
        $this->assertTrue(Asset::query()->whereOpenPosition()->whereKey($opcao->getKey())->exists());
        $this->assertFalse(Asset::query()->wherePositionPositive()->whereKey($opcao->getKey())->exists());
    }

    public function test_exercicio_de_call_comprada_gera_compra_do_ativo_objeto_pelo_strike(): void
    {
        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Corretora', 'kind' => 'broker', 'opening_balance' => 5000,
        ]);

        $opcao = $this->makeOption();
        $this->addTransaction($opcao, 'BUY', '2026-02-05', 100, 50.0, 'Credito');

        Livewire::test(EditAsset::class, ['record' => $opcao->getKey()])
            ->callAction('exercise', data: [
                'date' => '2026-06-19',
                'quantity' => 100,
                'account_id' => $conta->getKey(),
            ])
            ->assertHasNoActionErrors();

        $opcao = $opcao->fresh()->load('transactions');
        $this->assertEqualsWithDelta(0.0, $opcao->positionQuantity(), 1e-9);

        // O prêmio pago realiza como perda na opção...
        $vendas = $opcao->realizedSalesForYear(2026);
        $this->assertCount(1, $vendas);
        $this->assertEqualsWithDelta(-50.0, $vendas[0]['gain'], 1e-9);

        // ...e o ativo-objeto entra pelo strike (100 x 32,50 = 3.250).
        $acao = Asset::where('tenant_id', $this->tenant->getKey())
            ->where('ticker_or_code', 'PETR4')
            ->firstOrFail()
            ->load('transactions');
        $this->assertSame('STOCK', $acao->type);
        $this->assertEqualsWithDelta(100.0, $acao->positionQuantity(), 1e-9);
        $this->assertEqualsWithDelta(3250.0, $acao->purchaseValue(), 1e-6);

        // A conta pagou o strike.
        $this->assertEqualsWithDelta(1750.0, $conta->fresh()->load('transactions')->balance(), 1e-6);
    }

    public function test_exercicio_de_call_lancada_gera_venda_do_ativo_objeto_pelo_strike(): void
    {
        $opcao = $this->makeOption();
        $this->addTransaction($opcao, 'SELL', '2026-02-05', 100, 150.0, 'Debito');

        Livewire::test(EditAsset::class, ['record' => $opcao->getKey()])
            ->callAction('exercise', data: ['date' => '2026-06-19', 'quantity' => 100])
            ->assertHasNoActionErrors();

        $opcao = $opcao->fresh()->load('transactions');
        $this->assertEqualsWithDelta(0.0, $opcao->positionQuantity(), 1e-9);

        // O prêmio recebido realiza como ganho na opção.
        $vendas = $opcao->realizedSalesForYear(2026);
        $this->assertCount(1, $vendas);
        $this->assertEqualsWithDelta(150.0, $vendas[0]['gain'], 1e-9);

        // Lançador de call designado VENDE o ativo-objeto pelo strike.
        $acao = Asset::where('tenant_id', $this->tenant->getKey())
            ->where('ticker_or_code', 'PETR4')
            ->firstOrFail()
            ->load('transactions');
        $venda = $acao->transactions->firstWhere('type', 'SELL');
        $this->assertNotNull($venda);
        $this->assertEqualsWithDelta(3250.0, (float) $venda->total_amount, 1e-6);
        $this->assertSame('Debito', $venda->direction);
    }

    public function test_acao_de_vencimento_po_encerra_a_posicao_lancada(): void
    {
        $opcao = $this->makeOption();
        $this->addTransaction($opcao, 'SELL', '2026-02-05', 100, 200.0, 'Debito');

        Livewire::test(EditAsset::class, ['record' => $opcao->getKey()])
            ->callAction('expire', data: ['date' => '2026-06-19'])
            ->assertHasNoActionErrors();

        $opcao = $opcao->fresh()->load('transactions');
        $vendas = $opcao->realizedSalesForYear(2026);

        $this->assertEqualsWithDelta(0.0, $opcao->positionQuantity(), 1e-9);
        $this->assertCount(1, $vendas);
        $this->assertEqualsWithDelta(200.0, $vendas[0]['gain'], 1e-9);
    }

    public function test_ir_tributa_opcoes_a_15_por_cento_sem_isencao_de_20_mil(): void
    {
        $opcao = $this->makeOption();
        // Ganho de 1.000 com vendas bem abaixo de 20 mil: ainda assim há DARF.
        $this->addTransaction($opcao, 'SELL', '2026-02-05', 100, 1500.0, 'Debito');
        $this->addTransaction($opcao, 'BUY', '2026-03-10', 100, 500.0, 'Credito');

        $report = app(IrReport::class)->build($this->tenant, 2026);

        $mes = collect($report['ganhos'])->firstWhere('mes', 3);
        $this->assertNotNull($mes);
        $this->assertEqualsWithDelta(1000.0, $mes['opcoes']['ganho'], 1e-6);
        $this->assertFalse($mes['opcoes']['isento']);
        $this->assertEqualsWithDelta(150.0, $mes['opcoes']['darf'], 1e-6);
        $this->assertEqualsWithDelta(150.0, $mes['darf'], 1e-6);
    }

    private function makeOption(array $metadata = []): Asset
    {
        return Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => 'Call PETR4 R$ 32,50',
            'type' => 'OPTION',
            'ticker_or_code' => 'PETRR250',
            'currency' => 'BRL',
            'metadata' => $metadata + ['underlying' => 'PETR4', 'option_type' => 'CALL', 'strike' => 32.5],
        ]);
    }

    private function addTransaction(Asset $asset, string $type, string $date, float $quantity, float $total, string $direction): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'type' => $type,
            'transaction_date' => $date,
            'quantity' => $quantity,
            'total_amount' => $total,
            'direction' => $direction,
            'source' => 'manual',
        ]);
    }
}
