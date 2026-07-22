<?php

namespace Tests\Feature;

use App\Ai\MilhaVendedora;
use App\Jobs\MilhaVendedoraJob;
use App\Livewire\MilhaVendedoraChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Ai;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Chat de vendas da landing: abordagem proativa, fluxo assíncrono, histórico
 * na sessão e — crítico num endpoint público que consome API paga — os freios
 * de abuso (limite por sessão, por IP e de tamanho de conversa).
 */
class MilhaVendedoraTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_renderiza_o_chat_com_abordagem_proativa(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('milha-lp-bubble', escape: false)
            ->assertSee('assistente de IA do Milia Invest', escape: false);
    }

    public function test_landing_tem_a_secao_da_milha_e_a_copy_nova(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Conheça a Milha')
            ->assertSee('Milha, sua assistente de IA')
            ->assertSee('Nada acontece sem a sua aprovação');
    }

    public function test_enviar_pergunta_enfileira_job_com_delay_e_responde(): void
    {
        Queue::fake();

        $component = Livewire::test(MilhaVendedoraChat::class)
            ->set('input', 'Quanto custa?')
            ->call('send')
            ->assertSet('input', '');

        $this->assertTrue($component->instance()->isAwaiting());

        Queue::assertPushed(MilhaVendedoraJob::class, function (MilhaVendedoraJob $job): bool {
            return $job->prompt === 'Quanto custa?'
                && $job->delay !== null
                // O histórico do job não inclui a mensagem atual (ela é o prompt),
                // mas inclui a saudação proativa.
                && collect($job->history)->pluck('role')->doesntContain('user');
        });
    }

    public function test_fluxo_completo_com_fila_sync_responde_e_guarda_na_sessao(): void
    {
        Ai::fakeAgent(MilhaVendedora::class, ['Custa R$ 19,90 por mês, com teste grátis!']);

        Livewire::test(MilhaVendedoraChat::class)
            ->set('input', 'Quanto custa?')
            ->call('send')
            ->call('pollReply')
            ->assertSee('Quanto custa?')
            ->assertSee('19,90');

        $history = session('milha_lp.history');

        $this->assertSame('user', $history[1]['role']);
        $this->assertSame('assistant', $history[2]['role']);
        $this->assertStringContainsString('19,90', $history[2]['content']);
    }

    public function test_conversa_nao_grava_nada_no_banco(): void
    {
        Ai::fakeAgent(MilhaVendedora::class, ['Resposta']);

        Livewire::test(MilhaVendedoraChat::class)
            ->set('input', 'Oi')
            ->call('send')
            ->call('pollReply');

        $this->assertDatabaseCount('agent_conversations', 0);
        $this->assertDatabaseCount('agent_conversation_messages', 0);
    }

    public function test_limite_por_sessao_bloqueia_com_cta(): void
    {
        Queue::fake();

        $component = Livewire::test(MilhaVendedoraChat::class);

        foreach (range(1, MilhaVendedoraChat::SESSION_DAILY_LIMIT) as $i) {
            RateLimiter::hit('milha-lp:'.session()->getId(), 60 * 60 * 24);
        }

        $component->set('input', 'Mais uma?')->call('send');

        $component->assertSee('limite de mensagens');
        $this->assertFalse($component->instance()->isAwaiting());
        Queue::assertNothingPushed();
    }

    public function test_mensagem_gigante_e_vazia_sao_ignoradas(): void
    {
        Queue::fake();

        Livewire::test(MilhaVendedoraChat::class)
            ->set('input', str_repeat('a', 501))
            ->call('send');

        Livewire::test(MilhaVendedoraChat::class)
            ->set('input', '   ')
            ->call('send');

        Queue::assertNothingPushed();
    }

    public function test_conversa_longa_e_encerrada_com_cta(): void
    {
        Queue::fake();

        session(['milha_lp.history' => array_fill(0, 30, ['role' => 'user', 'content' => 'x'])]);

        Livewire::test(MilhaVendedoraChat::class)
            ->set('input', 'Mais uma')
            ->call('send')
            ->assertSee('criar minha conta');

        Queue::assertNothingPushed();
    }

    public function test_erro_da_ia_vira_mensagem_amigavel_com_cta(): void
    {
        Ai::fakeAgent(MilhaVendedora::class, fn () => throw new \RuntimeException('groq caiu'));

        Livewire::test(MilhaVendedoraChat::class)
            ->set('input', 'Oi')
            ->call('send')
            ->call('pollReply')
            ->assertSee('probleminha');
    }

    public function test_agente_monta_historico_como_mensagens_do_sdk(): void
    {
        $agente = new MilhaVendedora([
            ['role' => 'assistant', 'content' => 'Oi!'],
            ['role' => 'user', 'content' => 'Quanto custa?'],
        ]);

        $mensagens = collect($agente->messages());

        $this->assertCount(2, $mensagens);
        $this->assertInstanceOf(\Laravel\Ai\Messages\AssistantMessage::class, $mensagens[0]);
        $this->assertInstanceOf(\Laravel\Ai\Messages\UserMessage::class, $mensagens[1]);
        $this->assertStringContainsString('19,90', (new MilhaVendedora)->instructions());
    }
}
