<?php

namespace Tests\Feature;

use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Contas\Pages\ListContas;
use App\Filament\Resources\Lancamentos\Pages\ListLancamentos;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Testes de interação (Livewire/Filament): formulários e ações reais, não só
 * instanciação — cobre o caminho que o usuário percorre no painel.
 */
class FilamentUiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();

        $this->user = User::create(['name' => 'Lucas', 'email' => 'lucas@teste.dev', 'password' => 'secret-123']);
        $this->tenant->users()->attach($this->user);

        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->tenant);
    }

    public function test_cadastro_de_ativo_fisico_cria_o_bem_com_a_aquisicao_debitando_da_conta(): void
    {
        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Nubank', 'kind' => 'bank', 'opening_balance' => 60000,
        ]);

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'type' => 'VEHICLE',
                'name' => 'VW Fox',
                'acquisition_value' => 43000,
                'acquisition_date' => '2026-07-01',
                'acquisition_quantity' => 1,
                'acquisition_account_id' => $conta->getKey(),
                'metadata.depreciation_rate' => 20,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $fox = Asset::where('name', 'VW Fox')->firstOrFail();

        $this->assertSame('VEHICLE', $fox->type);
        // Métricas materializadas pelo observer no ato do lançamento.
        $this->assertEqualsWithDelta(1.0, (float) $fox->position_quantity, 1e-6);
        $this->assertEqualsWithDelta(43000.0, (float) $fox->invested_value, 1e-2);
        // A compra saiu do saldo da conta.
        $this->assertEqualsWithDelta(17000.0, $conta->fresh()->load('transactions')->balance(), 1e-6);
    }

    public function test_listagem_de_ativos_mostra_o_bem_na_tab_patrimonio(): void
    {
        $this->makeAssetWithBuy('Trator John Deere', 'MACHINERY', 100000);

        Livewire::test(ListAssets::class, ['activeTab' => 'physical'])
            ->assertSuccessful()
            ->assertSee('Trator John Deere');
    }

    public function test_acao_de_resgate_zera_a_posicao_e_credita_a_conta(): void
    {
        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Corretora', 'kind' => 'broker', 'opening_balance' => 0,
        ]);
        $cdb = $this->makeAssetWithBuy('CDB Banco X', 'FIXED_INCOME', 10000);

        Livewire::test(EditAsset::class, ['record' => $cdb->getKey()])
            ->callAction('redeem', data: [
                'date' => '2026-07-21',
                'net_amount' => 10850,
                'account_id' => $conta->getKey(),
            ])
            ->assertHasNoActionErrors();

        $this->assertEqualsWithDelta(0.0, $cdb->fresh()->load('transactions')->positionQuantity(), 1e-9);
        $this->assertEqualsWithDelta(10850.0, $conta->fresh()->load('transactions')->balance(), 1e-6);
    }

    public function test_lancamento_avulso_de_despesa_via_tela_de_lancamentos(): void
    {
        Livewire::test(ListLancamentos::class)
            ->callAction('create', data: [
                'type' => 'EXPENSE',
                'transaction_date' => '2026-07-21',
                'movement' => 'Assinatura Claude Code',
                'total_amount' => 100,
                'category' => 'Assinaturas & Software',
            ])
            ->assertHasNoActionErrors();

        $lancamento = Transaction::whereNull('asset_id')->firstOrFail();

        $this->assertSame('EXPENSE', $lancamento->type);
        $this->assertSame('Debito', $lancamento->direction);
        $this->assertSame('manual', $lancamento->source);
    }

    public function test_criacao_de_conta_via_tela_de_contas(): void
    {
        Livewire::test(ListContas::class)
            ->callAction('create', data: [
                'name' => 'Caixa da Fazenda',
                'kind' => 'cash',
                'currency' => 'BRL',
                'opening_balance' => 500,
            ])
            ->assertHasNoActionErrors();

        $conta = Account::where('name', 'Caixa da Fazenda')->firstOrFail();
        $this->assertEqualsWithDelta(500.0, $conta->load('transactions')->balance(), 1e-9);
    }

    public function test_edicao_manual_gera_registro_de_auditoria(): void
    {
        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Nubank', 'kind' => 'bank', 'opening_balance' => 100,
        ]);
        $conta->update(['opening_balance' => 250]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Account::class,
            'subject_id' => $conta->getKey(),
            'event' => 'updated',
        ]);
    }

    private function makeAssetWithBuy(string $name, string $type, float $total): Asset
    {
        $asset = Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => $name,
            'type' => $type,
            'currency' => 'BRL',
        ]);

        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'type' => 'BUY', 'transaction_date' => '2026-01-10', 'quantity' => 1,
            'total_amount' => $total, 'direction' => 'Credito', 'source' => 'manual',
        ]);

        return $asset;
    }
}
