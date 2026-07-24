<?php

namespace Tests\Feature;

use App\Enums\FlowDirection;
use App\Mail\TenantInvitationMail;
use App\Models\Asset;
use App\Models\RecurringTransaction;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FinancialIndependence;
use App\Services\UpcomingEvents;
use App\Services\YearInReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cobertura das features novas: Viver de Renda, Agenda do Mês, Diário de
 * Tese, Retrospectiva, link do contador e modo família.
 */
class NovasFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-21');

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();

        $this->user = User::create(['name' => 'Lucas Teste', 'email' => 'lucas@teste.dev', 'password' => 'secret-123']);
        $this->tenant->users()->attach($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---- Viver de Renda -------------------------------------------------

    public function test_projecao_de_independencia_encontra_o_marco(): void
    {
        $asset = $this->makeAsset('FII Teste', 'FII');
        $this->buy($asset, '2025-07-01', 100_000);

        // 12 meses de R$ 700 de renda → yield mensal de 0,7%.
        foreach (range(0, 11) as $i) {
            $this->income($asset, 'INCOME', Carbon::parse('2025-08-05')->addMonthsNoOverflow($i)->toDateString(), 700);
        }

        $this->tenant->forceFill([
            'independence_monthly_cost' => 1_400.00,
            'independence_monthly_contribution' => 2_000.00,
            'independence_expected_return' => 8.0,
            'independence_contribution_growth' => 5.0,
            'independence_inflation' => 4.0,
        ])->save();

        $data = app(FinancialIndependence::class)->build($this->tenant->fresh());

        $this->assertTrue($data['configurado']);
        $this->assertNotNull($data['meses_ate_independencia']);
        $this->assertGreaterThan(0, $data['meses_ate_independencia']);
        $this->assertNotNull($data['patrimonio_alvo']);
        // Cobertura atual: ~700 de renda média para 1.400 de custo ≈ 50%.
        $this->assertEqualsWithDelta(50.0, $data['cobertura_pct'], 5.0);
        // Aporte extra tem que encurtar (ou pelo menos não alongar) o caminho.
        $comExtra = app(FinancialIndependence::class)->build($this->tenant->fresh(), 3_000);
        $this->assertLessThanOrEqual($data['meses_ate_independencia'], $comExtra['meses_ate_independencia']);

        // Tabelas estilo calculadora de juros compostos: anual e mensal.
        $this->assertNotEmpty($data['table_anual']);
        $this->assertNotEmpty($data['table_mensal']);
        $this->assertSame(count($data['table_mensal']), ($data['resumo']['horizonte_anos'] * 12));

        $primeiroAno = $data['table_anual'][1];
        $this->assertGreaterThan(0, $primeiroAno['juros_no_ano']);
        // Total investido = patrimônio inicial (100k) + 12 aportes de 2k.
        $this->assertEqualsWithDelta(124_000.0, $primeiroAno['total_investido'], 1.0);
        // Reajuste de 5%: aporte do 2º ano = 2.100.
        $this->assertEqualsWithDelta(2_100.0, $data['table_anual'][2]['aporte_mensal'], 0.01);
        // Inflação de 4% corrige o custo de vida no 1º ano.
        $this->assertEqualsWithDelta(1_400 * 1.04, $primeiroAno['custo_mensal'], 1.0);
    }

    // ---- Agenda do Mês ---------------------------------------------------

    public function test_agenda_junta_recorrencias_vencimentos_e_proventos_estimados(): void
    {
        // Recorrência ativa (aluguel) no dia 10.
        RecurringTransaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'type' => 'INCOME',
            'description' => 'Aluguel galpão',
            'amount' => 3_000,
            'day_of_month' => 10,
            'starts_on' => '2026-01-01',
            'active' => true,
        ]);

        // Renda fixa vencendo dentro do mês.
        $rf = $this->makeAsset('CDB Teste', 'FIXED_INCOME', ['due_date' => '2026-07-28', 'indexer' => 'CDI']);
        $this->buy($rf, '2026-01-10', 5_000);

        // Despesa recorrente no dia 15 também entra.
        RecurringTransaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'type' => 'EXPENSE',
            'description' => 'Contabilidade',
            'amount' => 350,
            'day_of_month' => 15,
            'starts_on' => '2026-01-01',
            'active' => true,
        ]);

        // Proventos NÃO entram na agenda: sem fonte oficial, seria chute.
        $fii = $this->makeAsset('FII Pagador', 'FII');
        $this->buy($fii, '2025-12-01', 10_000);
        $this->income($fii, 'INCOME', '2026-06-13', 90);

        $agenda = app(UpcomingEvents::class)->month($this->tenant, '2026-07');
        $kinds = array_column($agenda['events'], 'kind');

        $this->assertContains('receita', $kinds);
        $this->assertContains('despesa', $kinds);
        $this->assertContains('vencimento', $kinds);
        $this->assertNotContains('provento', $kinds);

        $this->assertEqualsWithDelta(3_000.0, $agenda['totals']['a_receber'], 0.01);
        $this->assertEqualsWithDelta(350.0, $agenda['totals']['a_pagar'], 0.01);
    }

    // ---- Diário de Tese --------------------------------------------------

    public function test_tese_ganha_data_e_preco_ao_ser_registrada_e_alerta_apos_queda(): void
    {
        $asset = $this->makeAsset('Ação Teste', 'STOCK', [], 'TESE3');
        $this->buy($asset, '2026-01-10', 10_000, 100); // preço médio R$ 100

        // Preço atual conhecido: R$ 100 na história de cotações.
        $this->quote('TESE3', '2026-07-20', 100);

        $asset->refresh()->load('transactions');
        $asset->metadata = array_merge($asset->metadata ?? [], ['thesis' => 'Comprei pelo dividendo consistente.']);
        $asset->save();

        $meta = $asset->fresh()->metadata;
        $this->assertSame('2026-07-21', $meta['thesis_recorded_at']);
        $this->assertEqualsWithDelta(100.0, (float) $meta['thesis_price'], 0.01);

        // Preço despenca 20% → alerta de revisão de tese no sino.
        $this->quote('TESE3', '2026-07-21', 80);
        // O cache estático de preços por ticker precisa esquecer o valor antigo.
        (fn () => self::$priceCache = [])->call(new Asset);

        $this->artisan('portfolio:notify-alerts')->assertSuccessful();

        $this->assertTrue(
            $this->user->notifications()
                ->get()
                ->contains(fn ($n): bool => str_contains((string) json_encode($n->data), 'Revisite sua tese')),
        );
    }

    // ---- Retrospectiva ---------------------------------------------------

    public function test_retrospectiva_soma_renda_passiva_e_aportes_do_ano(): void
    {
        $asset = $this->makeAsset('FII Retro', 'FII');
        $this->buy($asset, '2025-02-01', 20_000);
        $this->income($asset, 'INCOME', '2025-03-10', 150);
        $this->income($asset, 'INCOME', '2025-06-10', 250);

        $review = app(YearInReview::class)->build($this->tenant, 2025);

        $this->assertSame(2025, $review['ano']);
        $this->assertEqualsWithDelta(400.0, $review['renda_passiva_total'], 0.01);
        $this->assertEqualsWithDelta(20_000.0, $review['aportes'], 0.01);
        $this->assertSame('junho', $review['melhor_mes']['label']);
        $this->assertSame('FII Retro', $review['top_ativos'][0]['nome']);
        $this->assertSame(3, $review['movimentacoes']);
    }

    // ---- Link do contador ------------------------------------------------

    public function test_link_assinado_do_contador_abre_o_relatorio_e_sem_assinatura_nega(): void
    {
        $asset = $this->makeAsset('Ação IR', 'STOCK', [], 'IRTS3');
        $this->buy($asset, '2025-03-01', 5_000);

        $signed = URL::temporarySignedRoute('contador.ir', now()->addDays(45), [
            'tenant' => $this->tenant->uuid,
            'year' => 2025,
        ]);

        $this->get($signed)
            ->assertOk()
            ->assertSee('Relatório para Imposto de Renda')
            ->assertSee('Tenant de Teste');

        $this->get(route('contador.ir', ['tenant' => $this->tenant->uuid, 'year' => 2025]))
            ->assertForbidden();
    }

    // ---- Modo família ----------------------------------------------------

    public function test_convite_e_aceito_e_vincula_o_usuario_ao_tenant(): void
    {
        Mail::fake();

        $invitation = TenantInvitation::createFor($this->tenant, 'conjuge@teste.dev', $this->user);

        Mail::to($invitation->email)->queue(new TenantInvitationMail($invitation));
        Mail::assertQueued(TenantInvitationMail::class);

        $convidado = User::create(['name' => 'Cônjuge', 'email' => 'conjuge@teste.dev', 'password' => 'secret-123']);

        // Página pública do convite.
        $this->get(route('convite.aceitar', ['token' => $invitation->token]))
            ->assertOk()
            ->assertSee('Tenant de Teste');

        // Aceite autenticado vincula ao tenant.
        $this->actingAs($convidado)
            ->post(route('convite.confirmar', ['token' => $invitation->token]))
            ->assertRedirect(url('/app/'.$this->tenant->getKey()));

        $this->assertTrue($convidado->fresh()->canAccessTenant($this->tenant));
        $this->assertNotNull($invitation->fresh()->accepted_at);

        // Convite não se aceita duas vezes.
        $this->actingAs($convidado)
            ->post(route('convite.confirmar', ['token' => $invitation->token]))
            ->assertStatus(410);
    }

    public function test_carta_de_patrimonio_exige_membro_do_tenant(): void
    {
        $asset = $this->makeAsset('Imóvel Teste', 'REAL_ESTATE');
        $this->buy($asset, '2025-01-01', 300_000);

        $this->actingAs($this->user)
            ->get(route('carta.patrimonio', ['tenant' => $this->tenant->uuid]))
            ->assertOk()
            ->assertSee('Carta de Patrimônio')
            ->assertSee('Imóvel Teste');

        $estranho = User::create(['name' => 'Estranho', 'email' => 'estranho@teste.dev', 'password' => 'secret-123']);

        $this->actingAs($estranho)
            ->get(route('carta.patrimonio', ['tenant' => $this->tenant->uuid]))
            ->assertForbidden();
    }

    // ---- Helpers -----------------------------------------------------------

    private function quote(string $ticker, string $date, float $close): void
    {
        \Illuminate\Support\Facades\DB::table('asset_price_history')->insert([
            'ticker' => $ticker, 'date' => $date, 'source' => 'teste',
            'price_open' => $close, 'price_close' => $close, 'price_high' => $close, 'price_low' => $close,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeAsset(string $name, string $type, array $metadata = [], ?string $ticker = null): Asset
    {
        return Asset::create([
            'tenant_id' => $this->tenant->getKey(),
            'name' => $name,
            'type' => $type,
            'ticker_or_code' => $ticker,
            'currency' => 'BRL',
            'metadata' => $metadata,
        ]);
    }

    private function buy(Asset $asset, string $date, float $total, ?float $unitPrice = null): Transaction
    {
        $quantity = $unitPrice !== null ? $total / $unitPrice : 1;

        return Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'type' => 'BUY',
            'transaction_date' => $date,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $total,
            'direction' => FlowDirection::Credit->value,
            'source' => 'manual',
        ]);
    }

    private function income(Asset $asset, string $type, string $date, float $total): Transaction
    {
        return Transaction::create([
            'tenant_id' => $this->tenant->getKey(),
            'asset_id' => $asset->getKey(),
            'type' => $type,
            'transaction_date' => $date,
            'quantity' => 1,
            'total_amount' => $total,
            'direction' => FlowDirection::Credit->value,
            'source' => 'manual',
        ]);
    }
}
