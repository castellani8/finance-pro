<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PrivacidadeEDados;
use App\Filament\Pages\RelatorioIr;
use App\Filament\Pages\RendaPassiva;
use App\Filament\Pages\Widgets\PassiveIncomeChart;
use App\Filament\Pages\Widgets\PassiveIncomeStats;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\Widgets\AssetStatsOverview;
use App\Filament\Resources\Auditoria\Pages\ListAuditoria;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Contas\Pages\ListContas;
use App\Filament\Resources\Lancamentos\Pages\ListLancamentos;
use App\Filament\Resources\Proventos\Pages\ListProventos;
use App\Filament\Resources\Recorrencias\Pages\ListRecorrencias;
use App\Filament\Widgets\AllocationChart;
use App\Filament\Widgets\CashFlowChart;
use App\Filament\Widgets\Onboarding;
use App\Filament\Widgets\MonthlyIncomeChart;
use App\Filament\Widgets\PortfolioEvolutionChart;
use App\Models\Account;
use App\Models\Asset;
use App\Models\Company;
use App\Models\RecurringTransaction;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CompanyFilter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Varredura de renderização: monta TODAS as páginas e widgets do painel com
 * dados de todos os tipos. Erros de configuração que só explodem na tela
 * (escopo de tenant, relação inexistente, coluna errada) quebram aqui.
 */
class PanelRenderSweepTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Asset $asset;

    private Company $company;

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

        $this->seedOneOfEverything();
    }

    /** Um registro de cada tipo, para as telas renderizarem com conteúdo real. */
    private function seedOneOfEverything(): void
    {
        $this->company = Company::create(['tenant_id' => $this->tenant->getKey(), 'name' => 'Fazenda X']);

        $conta = Account::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'Nubank', 'kind' => 'bank', 'opening_balance' => 1000,
        ]);

        $this->asset = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'company_id' => $this->company->getKey(),
            'name' => 'Trator', 'type' => 'MACHINERY', 'currency' => 'BRL',
            'metadata' => ['depreciation_rate' => 10],
        ]);

        $cdb = Asset::create([
            'tenant_id' => $this->tenant->getKey(), 'name' => 'CDB Banco X', 'type' => 'FIXED_INCOME',
            'currency' => 'BRL', 'metadata' => ['indexer' => 'CDI', 'index_percent' => 100, 'spread' => 0, 'due_date' => '2027-01-01'],
        ]);

        $tx = fn (Asset $asset, string $type, string $date, float $total, string $direction = 'Credito') => Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'asset_id' => $asset->getKey(),
            'type' => $type, 'transaction_date' => $date, 'quantity' => 1,
            'total_amount' => $total, 'direction' => $direction, 'source' => 'manual',
        ]);

        $tx($this->asset, 'BUY', '2025-01-10', 100000);
        $tx($this->asset, 'INCOME', '2025-06-05', 3000);
        $tx($this->asset, 'EXPENSE', '2025-07-01', 500, 'Debito');
        $tx($cdb, 'BUY', '2025-02-01', 10000);

        // Lançamento avulso + recorrência + auditoria (update manual).
        Transaction::create([
            'tenant_id' => $this->tenant->getKey(), 'account_id' => $conta->getKey(),
            'company_id' => $this->company->getKey(), 'type' => 'EXPENSE',
            'transaction_date' => '2025-07-10', 'quantity' => 1, 'total_amount' => 100,
            'direction' => 'Debito', 'category' => 'Assinaturas & Software',
            'movement' => 'Claude Code', 'source' => 'manual',
        ]);
        RecurringTransaction::create([
            'tenant_id' => $this->tenant->getKey(), 'company_id' => $this->company->getKey(),
            'type' => 'EXPENSE', 'description' => 'Assinatura', 'amount' => 100,
            'day_of_month' => 1, 'starts_on' => '2025-07-01', 'active' => true,
        ]);
        $this->company->update(['document' => '11222333000144']);
    }

    public function test_todas_as_paginas_do_painel_renderizam(): void
    {
        $pages = [
            Dashboard::class => [],
            RendaPassiva::class => [],
            RelatorioIr::class => [],
            PrivacidadeEDados::class => [],
            \App\Filament\Pages\ViverDeRenda::class => [],
            \App\Filament\Pages\AgendaDoMes::class => [],
            \App\Filament\Pages\Retrospectiva::class => [],
            \App\Filament\Pages\Familia::class => [],
            ListAssets::class => [],
            CreateAsset::class => [],
            EditAsset::class => ['record' => null],
            ListCompanies::class => [],
            CreateCompany::class => [],
            EditCompany::class => ['company' => null],
            ListContas::class => [],
            ListLancamentos::class => [],
            ListRecorrencias::class => [],
            ListProventos::class => [],
            ListAuditoria::class => [],
        ];

        foreach ($pages as $page => $params) {
            if (array_key_exists('record', $params)) {
                $params = ['record' => $this->asset->getKey()];
            }

            if (array_key_exists('company', $params)) {
                $params = ['record' => $this->company->getKey()];
            }

            Livewire::test($page, $params)->assertSuccessful();
        }
    }

    public function test_todos_os_widgets_do_dashboard_renderizam(): void
    {
        $widgets = [
            PortfolioEvolutionChart::class,
            MonthlyIncomeChart::class,
            AllocationChart::class,
            CashFlowChart::class,
            Onboarding::class,
            PassiveIncomeStats::class,
            PassiveIncomeChart::class,
        ];

        foreach ($widgets as $widget) {
            Livewire::test($widget)->assertSuccessful();
        }

        Livewire::test(AssetStatsOverview::class, [
            'record' => $this->asset,
        ])->assertSuccessful();
    }

    public function test_paginas_renderizam_com_filtro_de_empresa_no_dashboard(): void
    {
        Livewire::test(PortfolioEvolutionChart::class, [
            'pageFilters' => ['company_id' => (string) $this->company->getKey()],
        ])->assertSuccessful();

        Livewire::test(CashFlowChart::class, [
            'pageFilters' => ['company_id' => CompanyFilter::NONE],
        ])->assertSuccessful();
    }
}
