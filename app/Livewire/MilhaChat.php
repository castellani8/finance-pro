<?php

namespace App\Livewire;

use App\Ai\ChartSvg;
use App\Ai\Tools\GerarGrafico;
use App\Jobs\MilhaPromptJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Balão flutuante da Milha no painel. A chamada à IA roda em job na fila
 * (com 1s de "janela de arrependimento" — o botão vira stop) e o componente
 * busca a resposta via polling no cache. Toda ação revalida que o usuário
 * logado pertence ao tenant. Histórico vem das tabelas do laravel/ai.
 */
class MilhaChat extends Component
{
    /** Mensagens por usuário por dia — freio de custo da API. */
    public const DAILY_MESSAGE_LIMIT = 60;

    /** Nomes amigáveis das ações que pedem aprovação. */
    private const ACTION_LABELS = [
        'CriarLancamento' => 'Criar lançamento',
        'CadastrarAtivo' => 'Cadastrar ativo',
        'RegistrarOperacao' => 'Registrar operação',
        'CriarRecorrencia' => 'Criar recorrência',
        'CriarConta' => 'Criar conta',
    ];

    #[Locked]
    public int $tenantId;

    #[Locked]
    public ?string $conversationId = null;

    /** @var array<int, array{role: string, html: string}> */
    #[Locked]
    public array $messages = [];

    /** @var array<int, array{id: string, tool: string, label: string, arguments: array<string, mixed>}> */
    #[Locked]
    public array $pending = [];

    /** Chave do turno em andamento (job na fila); null = ocioso. */
    #[Locked]
    public ?string $replyKey = null;

    public string $input = '';

    public function mount(int $tenantId): void
    {
        $this->tenantId = $tenantId;

        $this->loadLatestConversation();
    }

    public function send(): void
    {
        $text = trim($this->input);

        if ($text === '' || mb_strlen($text) > 2000 || $this->pending !== [] || $this->isAwaiting()) {
            return;
        }

        $user = $this->user();

        if (! RateLimiter::attempt('milha:'.$user->getKey(), self::DAILY_MESSAGE_LIMIT, fn () => null, 60 * 60 * 24)) {
            $this->messages[] = $this->assistantMessage(
                'Ufa, conversamos bastante hoje! Atingimos o limite diário de mensagens — amanhã continuamos. 💛'
            );

            return;
        }

        $this->input = '';
        $this->messages[] = ['role' => 'user', 'html' => e($text)];

        $this->dispatchTurn($text, null);

        // Ao enviar, rola até a própria mensagem; a resposta NÃO rola sozinha.
        $this->dispatch('milha-scroll');
    }

    /** Janela de arrependimento: cancela o turno enfileirado. */
    public function stop(): void
    {
        if ($this->replyKey === null) {
            return;
        }

        Cache::put(MilhaPromptJob::cancelKey($this->replyKey), true, 600);

        $this->replyKey = null;
    }

    public function approve(): void
    {
        $this->decide('approve');
    }

    public function reject(): void
    {
        $this->decide('reject');
    }

    /** Chamado pelo wire:poll enquanto um turno está na fila. */
    public function pollReply(): void
    {
        if ($this->replyKey === null) {
            return;
        }

        $payload = Cache::pull(MilhaPromptJob::replyCacheKey($this->replyKey));

        if ($payload === null) {
            return;
        }

        $this->replyKey = null;

        if (($payload['status'] ?? null) === 'error') {
            $this->messages[] = $this->assistantMessage(
                'Opa, tive um probleminha para responder agora. Pode tentar de novo em instantes?'
            );

            return;
        }

        if (($payload['status'] ?? null) !== 'done') {
            return; // cancelado — não há o que exibir
        }

        $this->conversationId = $payload['conversationId'] ?? $this->conversationId;

        $firstNewIndex = count($this->messages);

        foreach ((array) ($payload['charts'] ?? []) as $spec) {
            $this->messages[] = ['role' => 'chart', 'html' => ChartSvg::render($spec)];
        }

        if (trim((string) ($payload['text'] ?? '')) !== '') {
            $this->messages[] = ['role' => 'assistant', 'html' => $this->markdown($payload['text'])];
        }

        $this->pending = collect((array) ($payload['pending'] ?? []))
            ->map(fn (array $approval): array => [
                'id' => (string) $approval['id'],
                'tool' => (string) $approval['tool'],
                'label' => self::ACTION_LABELS[$approval['tool']] ?? (string) $approval['tool'],
                'arguments' => (array) $approval['arguments'],
            ])
            ->all();

        // Rola até o INÍCIO da resposta nova — ler do começo, sem caçar o scroll.
        $this->dispatch('milha-scroll-start', index: $firstNewIndex);
    }

    public function newConversation(): void
    {
        $this->stop();

        $this->conversationId = null;
        $this->messages = [];
        $this->pending = [];
    }

    public function isAwaiting(): bool
    {
        return $this->replyKey !== null;
    }

    public function render()
    {
        return view('livewire.milha-chat');
    }

    private function decide(string $decision): void
    {
        if ($this->pending === [] || $this->conversationId === null || $this->isAwaiting()) {
            return;
        }

        $this->pending = [];

        $this->dispatchTurn(null, $decision);
    }

    /** Enfileira um turno da Milha; o 1s de delay é a janela do botão stop. */
    private function dispatchTurn(?string $prompt, ?string $decision): void
    {
        $this->replyKey = (string) Str::uuid();

        MilhaPromptJob::dispatch(
            $this->tenant(),
            $this->user(),
            $this->conversationId,
            $prompt,
            $decision,
            $this->replyKey,
        )->delay(now()->addSecond());
    }

    /** O tenant só vale se o usuário logado pertencer a ele. */
    private function tenant(): Tenant
    {
        return $this->user()->tenants()->whereKey($this->tenantId)->firstOrFail();
    }

    private function user(): User
    {
        return auth()->user();
    }

    private function loadLatestConversation(): void
    {
        $user = $this->user();

        $conversation = Conversation::query()
            ->where('participant_type', $user->getMorphClass())
            ->where('participant_id', $user->getKey())
            ->latest('updated_at')
            ->first();

        if ($conversation === null) {
            return;
        }

        $this->conversationId = $conversation->getKey();

        $this->messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->oldest('created_at')
            ->limit(60)
            ->get()
            ->flatMap(function ($message): array {
                if ($message->role === 'user') {
                    return trim((string) $message->content) === ''
                        ? []
                        : [['role' => 'user', 'html' => e($message->content)]];
                }

                // Gráficos gerados na conversa voltam a partir dos tool_calls
                // persistidos, na mesma posição (antes do comentário).
                $items = $this->chartsFromToolCalls((array) ($message->tool_calls ?? []));

                if (trim((string) $message->content) !== '') {
                    $items[] = ['role' => 'assistant', 'html' => $this->markdown($message->content)];
                }

                return $items;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array<int, array{role: string, html: string}>
     */
    private function chartsFromToolCalls(array $toolCalls): array
    {
        $charts = [];

        foreach ($toolCalls as $call) {
            if (($call['name'] ?? null) !== 'GerarGrafico') {
                continue;
            }

            [$spec] = GerarGrafico::normalize((array) ($call['arguments'] ?? []));

            if ($spec !== null) {
                $charts[] = ['role' => 'chart', 'html' => ChartSvg::render($spec)];
            }
        }

        return $charts;
    }

    /** Markdown do assistente → HTML seguro (tags cruas são descartadas). */
    private function markdown(string $text): string
    {
        return (string) Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 6,
        ]);
    }

    /** @return array{role: string, html: string} */
    private function assistantMessage(string $text): array
    {
        return ['role' => 'assistant', 'html' => $this->markdown($text)];
    }
}
