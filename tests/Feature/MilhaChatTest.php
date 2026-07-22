<?php

namespace Tests\Feature;

use App\Ai\Milha;
use App\Jobs\MilhaPromptJob;
use App\Livewire\MilhaChat;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O componente de chat da Milha: renderização no painel, o fluxo assíncrono
 * (job na fila + polling + janela de arrependimento do stop), persistência
 * de conversa e as proteções de tenant. Na suíte a fila é sync, então o job
 * roda no ato do dispatch e o pollReply busca o resultado do cache.
 */
class MilhaChatTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();

        $this->user = User::create([
            'name' => 'Lucas',
            'email' => 'lucas@teste.dev',
            'password' => 'secret-123',
        ]);
        $this->user->markEmailAsVerified();
        $this->tenant->users()->attach($this->user);
    }

    public function test_balao_da_milha_renderiza_no_painel(): void
    {
        $response = $this->actingAs($this->user)->get('/app/'.$this->tenant->getKey());

        $response->assertOk();
        $response->assertSee('milha-bubble', escape: false);
    }

    public function test_balao_nao_renderiza_na_tela_de_login(): void
    {
        $this->get('/app/login')
            ->assertOk()
            ->assertDontSee('milha-bubble', escape: false);
    }

    public function test_send_enfileira_job_com_delay_de_um_segundo(): void
    {
        Queue::fake();

        $component = Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', 'Como estou indo?')
            ->call('send')
            ->assertSet('input', '');

        $this->assertTrue($component->instance()->isAwaiting());

        Queue::assertPushed(MilhaPromptJob::class, function (MilhaPromptJob $job): bool {
            return $job->prompt === 'Como estou indo?'
                && $job->delay !== null
                && now()->diffInSeconds($job->delay, absolute: true) <= 2;
        });
    }

    public function test_enviar_mensagem_responde_e_persiste_conversa(): void
    {
        Ai::fakeAgent(Milha::class, ['Você está indo **muito bem**, Lucas!']);

        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', 'Como estou indo?')
            ->call('send')       // fila sync: o job roda aqui mesmo
            ->call('pollReply')  // e o polling coleta o resultado do cache
            ->assertSee('Como estou indo?')
            ->assertSee('muito bem');

        $this->assertDatabaseCount('agent_conversations', 1);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'role' => 'user',
            'participant_id' => $this->user->getKey(),
        ]);
    }

    public function test_poll_sem_resposta_pronta_mantem_a_espera(): void
    {
        Queue::fake();

        $component = Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', 'Oi')
            ->call('send')
            ->call('pollReply');

        $this->assertTrue($component->instance()->isAwaiting());
    }

    public function test_stop_cancela_o_turno_enfileirado(): void
    {
        Queue::fake();

        $component = Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', 'Oi Milha')
            ->call('send');

        $replyKey = $component->get('replyKey');
        $this->assertNotNull($replyKey);

        $component->call('stop');

        $this->assertFalse($component->instance()->isAwaiting());

        // O job roda depois (worker) e encontra o flag: aborta sem chamar a IA.
        (new MilhaPromptJob($this->tenant, $this->user, null, 'Oi Milha', null, $replyKey))->handle();

        $this->assertSame(
            ['status' => 'cancelled'],
            Cache::get(MilhaPromptJob::replyCacheKey($replyKey)),
        );
        $this->assertDatabaseCount('agent_conversations', 0);
    }

    public function test_historico_da_conversa_volta_ao_remontar_o_componente(): void
    {
        Ai::fakeAgent(Milha::class, ['Primeira resposta da Milha']);

        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', 'Primeira pergunta')
            ->call('send')
            ->call('pollReply');

        // Nova visita: o mount() deve recarregar a última conversa do usuário.
        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->assertSee('Primeira pergunta')
            ->assertSee('Primeira resposta da Milha');
    }

    public function test_mensagem_vazia_ou_gigante_nao_enfileira_nada(): void
    {
        Queue::fake();

        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', '   ')
            ->call('send')
            ->assertSet('messages', []);

        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', str_repeat('a', 2001))
            ->call('send')
            ->assertSet('messages', []);

        Queue::assertNothingPushed();
    }

    public function test_tenant_id_e_propriedade_travada(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('tenantId', 999);
    }

    public function test_usuario_de_outro_tenant_nao_consegue_usar_o_chat(): void
    {
        $intruso = User::create([
            'name' => 'Intruso',
            'email' => 'intruso@teste.dev',
            'password' => 'secret-123',
        ]);
        $intruso->markEmailAsVerified();
        // Intruso não pertence ao tenant — o componente deve recusar a ação.

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($intruso)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', 'Oi')
            ->call('send');
    }

    public function test_aprovar_sem_pendencia_e_no_op(): void
    {
        Queue::fake();

        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->call('approve')
            ->assertSet('messages', []);

        Queue::assertNothingPushed();
    }

    public function test_nova_conversa_limpa_o_estado(): void
    {
        Ai::fakeAgent(Milha::class, ['Resposta qualquer']);

        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', 'Oi Milha')
            ->call('send')
            ->call('pollReply')
            ->call('newConversation')
            ->assertSet('messages', [])
            ->assertSet('conversationId', null);
    }

    public function test_erro_da_ia_vira_mensagem_amigavel(): void
    {
        Ai::fakeAgent(Milha::class, fn () => throw new \RuntimeException('groq caiu'));

        Livewire::actingAs($this->user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()])
            ->set('input', 'Oi')
            ->call('send')
            ->call('pollReply')
            ->assertSee('probleminha');
    }
}
