<?php

namespace Tests\Feature;

use App\Ai\ChartRegistry;
use App\Ai\ChartSvg;
use App\Ai\Tools\GerarGrafico;
use App\Livewire\MilhaChat;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gráficos da Milha: validação da tool, registro na request, SVG gerado e
 * reidratação do histórico a partir dos tool_calls persistidos.
 */
class MilhaGraficoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = new Tenant;
        $this->tenant->forceFill(['name' => 'Tenant de Teste', 'uuid' => (string) Str::uuid()])->save();
    }

    public function test_tool_valida_spec_e_empilha_no_registry(): void
    {
        $tool = new GerarGrafico($this->tenant);

        $invalidos = [
            ['tipo' => 'radar', 'titulo' => 'x', 'labels' => ['a'], 'series' => [['valores' => [1]]]],
            ['tipo' => 'linha', 'titulo' => '', 'labels' => ['a'], 'series' => [['valores' => [1]]]],
            ['tipo' => 'linha', 'titulo' => 'x', 'labels' => ['a', 'b'], 'series' => [['valores' => [1]]]],
            ['tipo' => 'pizza', 'titulo' => 'x', 'labels' => ['a'], 'series' => [['valores' => [-5]]]],
            ['tipo' => 'linha', 'titulo' => 'x', 'labels' => ['a'], 'series' => [['valores' => ['abc']]]],
        ];

        foreach ($invalidos as $args) {
            $result = json_decode($tool->handle(new Request($args)), true);
            $this->assertArrayHasKey('erro', $result, 'Deveria rejeitar: '.json_encode($args));
        }

        $this->assertSame([], app(ChartRegistry::class)->flush());

        $ok = json_decode($tool->handle(new Request([
            'tipo' => 'barras',
            'titulo' => 'Receitas x Despesas',
            'labels' => ['mai/26', 'jun/26'],
            'series' => [
                ['nome' => 'Receitas', 'valores' => [5550, 3800]],
                ['nome' => 'Despesas', 'valores' => [1200, 900]],
            ],
        ])), true);

        $this->assertTrue($ok['sucesso']);

        $charts = app(ChartRegistry::class)->flush();

        $this->assertCount(1, $charts);
        $this->assertSame('barras', $charts[0]['tipo']);
        $this->assertSame([5550.0, 3800.0], $charts[0]['series'][0]['valores']);

        // flush() drena: segunda leitura vem vazia.
        $this->assertSame([], app(ChartRegistry::class)->flush());
    }

    public function test_svg_e_gerado_para_os_tres_tipos(): void
    {
        $specs = [
            ['tipo' => 'barras', 'titulo' => 'Barras', 'labels' => ['a', 'b'], 'series' => [['nome' => 'S', 'valores' => [10.0, -5.0]]], 'moeda' => true],
            ['tipo' => 'linha', 'titulo' => 'Linha', 'labels' => ['a', 'b', 'c'], 'series' => [['nome' => 'S1', 'valores' => [1.0, 2.0, 3.0]], ['nome' => 'S2', 'valores' => [3.0, 2.0, 1.0]]], 'moeda' => false],
            ['tipo' => 'pizza', 'titulo' => 'Pizza', 'labels' => ['Ações', 'FIIs'], 'series' => [['nome' => 'S', 'valores' => [70.0, 30.0]]], 'moeda' => true],
        ];

        foreach ($specs as $spec) {
            $svg = ChartSvg::render($spec);

            $this->assertStringStartsWith('<svg', $svg);
            $this->assertStringEndsWith('</svg>', $svg);
            $this->assertStringContainsString($spec['titulo'], $svg);
            $this->assertStringNotContainsString('<script', $svg);
        }
    }

    public function test_labels_sao_escapados_no_svg(): void
    {
        $svg = ChartSvg::render([
            'tipo' => 'pizza',
            'titulo' => '<img src=x onerror=alert(1)>',
            'labels' => ['<b>x</b>', 'ok'],
            'series' => [['nome' => 'S', 'valores' => [1.0, 2.0]]],
            'moeda' => false,
        ]);

        $this->assertStringNotContainsString('<img', $svg);
        $this->assertStringNotContainsString('<b>', $svg);
    }

    public function test_historico_reidrata_graficos_dos_tool_calls(): void
    {
        $user = User::create(['name' => 'Lucas', 'email' => 'lucas@teste.dev', 'password' => 'secret-123']);
        $user->markEmailAsVerified();
        $this->tenant->users()->attach($user);

        $conversation = Conversation::create([
            'id' => (string) Str::uuid(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'title' => 'Teste',
        ]);

        ConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->getKey(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'agent' => \App\Ai\Milha::class,
            'role' => 'assistant',
            'content' => 'Olha a evolução!',
            'attachments' => [],
            'tool_calls' => [[
                'id' => 'fc_1',
                'name' => 'GerarGrafico',
                'arguments' => [
                    'tipo' => 'linha',
                    'titulo' => 'Renda passiva',
                    'labels' => ['jun/26', 'jul/26'],
                    'series' => [['nome' => 'Total', 'valores' => [230.5, 192.1]]],
                ],
            ]],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);

        $component = Livewire::actingAs($user)
            ->test(MilhaChat::class, ['tenantId' => $this->tenant->getKey()]);

        $messages = $component->get('messages');

        $this->assertSame('chart', $messages[0]['role']);
        $this->assertStringContainsString('Renda passiva', $messages[0]['html']);
        $this->assertSame('assistant', $messages[1]['role']);
    }
}
