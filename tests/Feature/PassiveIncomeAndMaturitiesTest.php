<?php

namespace Tests\Feature;

use App\Enums\FlowDirection;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PassiveIncome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PassiveIncomeAndMaturitiesTest extends TestCase
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

    public function test_renda_passiva_separa_alugueis_proventos_e_juros(): void
    {
        $galpao = $this->makeAsset('Galpão', 'REAL_ESTATE');
        $acao = $this->makeAsset('Ação', 'STOCK');
        $cdb = $this->makeAsset('CDB', 'FIXED_INCOME');

        $this->income($galpao, 'INCOME', 3000);   // aluguel de bem físico
        $this->income($acao, 'DIVIDEND', 500);    // provento de bolsa
        $this->income($acao, 'INCOME', 200);      // rendimento de papel -> provento
        $this->income($cdb, 'INTEREST', 150);     // juros de renda fixa

        $series = app(PassiveIncome::class)->monthly($this->tenant, 3);

        $this->assertEqualsWithDelta(3000.0, end($series['alugueis']), 1e-9);
        $this->assertEqualsWithDelta(700.0, end($series['proventos']), 1e-9);
        $this->assertEqualsWithDelta(150.0, end($series['juros']), 1e-9);
        $this->assertEqualsWithDelta(3850.0, end($series['total']), 1e-9);
    }

    public function test_notifica_vencimento_de_renda_fixa_nos_limiares(): void
    {
        $user = User::create(['name' => 'Lucas', 'email' => 'lucas@teste.dev', 'password' => 'secret-123']);
        $this->tenant->users()->attach($user);

        // Vence em exatamente 7 dias, com posição: notifica.
        $venceEm7 = $this->makeAsset('CDB Vencendo', 'FIXED_INCOME', ['due_date' => '2026-07-28']);
        $this->buy($venceEm7, 1000);

        // Vence em 15 dias (fora dos limiares 30/7/0): não notifica.
        $venceEm15 = $this->makeAsset('CDB Longe', 'FIXED_INCOME', ['due_date' => '2026-08-05']);
        $this->buy($venceEm15, 1000);

        // Vence em 7 dias mas já foi resgatado (posição zero): não notifica.
        $zerado = $this->makeAsset('CDB Zerado', 'FIXED_INCOME', ['due_date' => '2026-07-28']);
        $this->buy($zerado, 1000);
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $zerado->getKey(),
            'type' => 'SELL', 'transaction_date' => '2026-07-01', 'quantity' => 1,
            'total_amount' => 1050, 'direction' => FlowDirection::Debit->value, 'source' => 'manual',
        ]);

        $this->artisan('portfolio:notify-alerts')->assertSuccessful();

        $notifications = DB::table('notifications')->get();
        $this->assertCount(1, $notifications);
        $this->assertStringContainsString('CDB Vencendo', (string) $notifications[0]->data);
    }

    public function test_resgate_com_conta_zera_posicao_e_credita_o_liquido(): void
    {
        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Nubank', 'kind' => 'bank', 'opening_balance' => 0,
        ]);
        $cdb = $this->makeAsset('CDB', 'FIXED_INCOME');
        $this->buy($cdb, 10000);

        // Mesma transação que a ação "Registrar resgate" cria.
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $cdb->getKey(),
            'account_id' => $conta->getKey(), 'type' => 'SELL', 'transaction_date' => '2026-07-21',
            'quantity' => 1, 'total_amount' => 10850, 'direction' => FlowDirection::Debit->value,
            'movement' => 'Resgate / Vencimento', 'source' => 'manual',
        ]);

        $cdb = $cdb->fresh()->load('transactions');
        $this->assertEqualsWithDelta(0.0, $cdb->positionQuantity(), 1e-9);
        // O líquido (com juros, já descontado IR) caiu na conta.
        $this->assertEqualsWithDelta(10850.0, $conta->fresh()->load('transactions')->balance(), 1e-9);
    }

    private function makeAsset(string $name, string $type, array $metadata = []): Asset
    {
        return Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => $name,
            'type' => $type,
            'currency' => 'BRL',
            'metadata' => $metadata,
        ]);
    }

    private function buy(Asset $asset, float $total): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'type' => 'BUY', 'transaction_date' => '2026-01-10', 'quantity' => 1,
            'total_amount' => $total, 'direction' => FlowDirection::Credit->value, 'source' => 'manual',
        ]);
    }

    private function income(Asset $asset, string $type, float $total): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'type' => $type, 'transaction_date' => '2026-07-10', 'quantity' => 1,
            'total_amount' => $total, 'direction' => FlowDirection::Credit->value, 'source' => 'manual',
        ]);
    }
}
