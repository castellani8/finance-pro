<?php

namespace Tests\Feature;

use App\Ai\MilhaVendedora;
use App\Ai\Tools\RegistrarFeedback;
use App\Jobs\MilhaVendedoraJob;
use App\Livewire\MilhaVendedoraChat;
use App\Models\MilhaFeedback;
use App\Models\MilhaLeadConversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feedback coletado pela Milha no painel e rastreabilidade dos leads da
 * vendedora: mensagens, tokens e cliques de CTA no banco.
 */
class MilhaFeedbackELeadsTest extends TestCase
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
        $this->user->markEmailAsVerified();
        $this->tenant->users()->attach($this->user);
    }

    public function test_registrar_feedback_valida_e_grava(): void
    {
        $tool = new RegistrarFeedback($this->tenant, $this->user);

        $invalido = json_decode($tool->handle(new Request(['tipo' => 'xingamento', 'mensagem' => 'x'])), true);
        $this->assertArrayHasKey('erro', $invalido);

        $semMensagem = json_decode($tool->handle(new Request(['tipo' => 'bug', 'mensagem' => '  '])), true);
        $this->assertArrayHasKey('erro', $semMensagem);

        $ok = json_decode($tool->handle(new Request([
            'tipo' => 'reclamacao',
            'mensagem' => 'A tela de proventos demora para carregar',
            'contexto' => 'Proventos',
        ])), true);

        $this->assertTrue($ok['sucesso']);
        $this->assertDatabaseHas('milha_feedback', [
            'tenant_id' => $this->tenant->getKey(),
            'user_id' => $this->user->getKey(),
            'tipo' => 'reclamacao',
            'contexto' => 'Proventos',
        ]);
    }

    public function test_feedback_nao_exige_aprovacao(): void
    {
        // Fricção mataria a coleta: a tool não implementa Approvable.
        $this->assertNotInstanceOf(
            \Laravel\Ai\Contracts\Approvable::class,
            new RegistrarFeedback($this->tenant, $this->user),
        );
    }

    public function test_conversa_do_lead_e_rastreada_no_banco_com_tokens(): void
    {
        Ai::fakeAgent(MilhaVendedora::class, ['Custa R$ 19,90/mês!']);

        Livewire::test(MilhaVendedoraChat::class)
            ->set('input', 'Quanto custa?')
            ->call('send')
            ->call('pollReply');

        $conversation = MilhaLeadConversation::sole();

        $this->assertNotNull($conversation->ip);

        $roles = $conversation->messages()->orderBy('id')->pluck('role')->all();
        // saudação proativa + pergunta do lead + resposta gravada pelo job
        $this->assertSame(['assistant', 'user', 'assistant'], $roles);

        $this->assertSame(
            'Quanto custa?',
            $conversation->messages()->where('role', 'user')->value('content'),
        );
    }

    public function test_pageview_sem_interacao_nao_cria_registro(): void
    {
        Livewire::test(MilhaVendedoraChat::class);

        $this->assertDatabaseCount('milha_lead_conversations', 0);
    }

    public function test_clique_no_cta_registra_horario_e_redireciona(): void
    {
        $component = Livewire::test(MilhaVendedoraChat::class)
            ->call('ctaClick')
            ->assertRedirect(url('/app/register'));

        $conversation = MilhaLeadConversation::sole();

        $this->assertSame(1, (int) $conversation->cta_clicks);
        $this->assertNotNull($conversation->cta_first_clicked_at);

        // Segundo clique: contador sobe, primeiro horário preservado.
        $primeiro = $conversation->cta_first_clicked_at;

        Livewire::test(MilhaVendedoraChat::class)->call('ctaClick');

        $conversation->refresh();
        $this->assertSame(2, (int) $conversation->cta_clicks);
        $this->assertTrue($conversation->cta_first_clicked_at->equalTo($primeiro));
    }

    public function test_job_grava_resposta_e_tokens_mesmo_sem_poll(): void
    {
        Ai::fakeAgent(MilhaVendedora::class, ['Resposta rastreada']);

        $conversation = MilhaLeadConversation::create(['session_id' => 'sess-teste', 'ip' => '127.0.0.1']);

        (new MilhaVendedoraJob([], 'Oi', (string) Str::uuid(), $conversation->getKey()))->handle();

        $mensagem = $conversation->messages()->where('role', 'assistant')->sole();

        $this->assertSame('Resposta rastreada', $mensagem->content);
        $this->assertNotNull($mensagem->prompt_tokens);
    }

    public function test_avatar_da_milha_esta_nos_dois_chats(): void
    {
        // "milha-avatar" cobre tanto o .jpg (foto oficial) quanto o .svg (fallback).
        $this->get('/')->assertSee('milha-avatar', escape: false);

        $this->actingAs($this->user)
            ->get('/app/'.$this->tenant->getKey())
            ->assertSee('milha-avatar', escape: false);
    }
}
