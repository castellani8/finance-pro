<?php

namespace App\Livewire;

use App\Jobs\MilhaVendedoraJob;
use App\Models\MilhaLeadConversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chat de vendas da landing page: visitante anônimo conversa com a Milha
 * vendedora. Histórico vive na sessão (nada no banco), a chamada à IA roda
 * em job na fila com janela de arrependimento, e o abuso é freado por rate
 * limit duplo (sessão + IP) — é um endpoint público que consome API paga.
 */
class MilhaVendedoraChat extends Component
{
    public const SESSION_DAILY_LIMIT = 20;

    public const IP_DAILY_LIMIT = 60;

    private const MAX_HISTORY = 30;

    private const SESSION_KEY = 'milha_lp.history';

    private const GREETING = 'Oi! 👋 Eu sou a **Milha**, a assistente de IA do Milia Invest. '
        .'Vi que você está dando uma olhada por aqui… Posso te fazer uma pergunta rápida: '
        .'hoje você acompanha seus investimentos onde — planilha, corretora, ou "de cabeça"? '
        .'Me conta que eu te mostro como deixar isso no automático. 😉';

    /** @var array<int, array{role: string, html: string}> */
    #[Locked]
    public array $messages = [];

    #[Locked]
    public ?string $replyKey = null;

    public string $input = '';

    public function mount(): void
    {
        if (session(self::SESSION_KEY) === null) {
            session([self::SESSION_KEY => [['role' => 'assistant', 'content' => self::GREETING]]]);
        }

        $this->messages = collect(session(self::SESSION_KEY))
            ->map(fn (array $message): array => $message['role'] === 'user'
                ? ['role' => 'user', 'html' => e($message['content'])]
                : ['role' => 'assistant', 'html' => $this->markdown($message['content'])])
            ->all();
    }

    public function send(): void
    {
        $text = trim($this->input);

        if ($text === '' || mb_strlen($text) > 500 || $this->isAwaiting()) {
            return;
        }

        if (count(session(self::SESSION_KEY, [])) >= self::MAX_HISTORY) {
            $this->pushAssistant('A gente já conversou bastante por aqui! 💛 O próximo passo é ver com os '
                .'seus próprios dados: [criar minha conta grátis](/app/register) — leva um minuto.');

            return;
        }

        $withinLimits = RateLimiter::attempt('milha-lp:'.session()->getId(), self::SESSION_DAILY_LIMIT, fn () => null, 60 * 60 * 24)
            && RateLimiter::attempt('milha-lp-ip:'.request()->ip(), self::IP_DAILY_LIMIT, fn () => null, 60 * 60 * 24);

        if (! $withinLimits) {
            $this->pushAssistant('Alcançamos o limite de mensagens por hoje! Mas o melhor teste é com os seus '
                .'dados: [crie sua conta grátis](/app/register) — '.config('landing.plan.trial_days').' dias, sem cartão. 💛');

            return;
        }

        $history = session(self::SESSION_KEY, []);

        $this->input = '';
        $this->messages[] = ['role' => 'user', 'html' => e($text)];

        session([self::SESSION_KEY => [...$history, ['role' => 'user', 'content' => $text]]]);

        // Rastreabilidade: a pergunta do lead vai para o banco.
        $this->conversation()->messages()->create(['role' => 'user', 'content' => $text]);

        $this->replyKey = (string) Str::uuid();

        // O histórico enviado ao job NÃO inclui a mensagem atual — ela é o prompt.
        MilhaVendedoraJob::dispatch($history, $text, $this->replyKey, $this->conversation()->getKey())
            ->delay(now()->addSecond());

        $this->dispatch('milha-scroll');
    }

    /** Clique em qualquer CTA de cadastro do chat: registra e redireciona. */
    public function ctaClick(): void
    {
        $this->conversation()->registerCtaClick();

        $this->redirect(url('/app/register'));
    }

    /** Janela de arrependimento: cancela o turno enfileirado. */
    public function stop(): void
    {
        if ($this->replyKey === null) {
            return;
        }

        Cache::put(MilhaVendedoraJob::cancelKey($this->replyKey), true, 600);

        $this->replyKey = null;
    }

    /** Chamado pelo wire:poll enquanto um turno está na fila. */
    public function pollReply(): void
    {
        if ($this->replyKey === null) {
            return;
        }

        $payload = Cache::pull(MilhaVendedoraJob::replyCacheKey($this->replyKey));

        if ($payload === null) {
            return;
        }

        $this->replyKey = null;

        if (($payload['status'] ?? null) === 'error') {
            $this->pushAssistant('Opa, tive um probleminha aqui. Pode tentar de novo? Ou, se preferir, '
                .'já vai direto ao ponto: [criar conta grátis](/app/register). 😉');

            return;
        }

        if (($payload['status'] ?? null) !== 'done' || trim((string) ($payload['text'] ?? '')) === '') {
            return;
        }

        $firstNewIndex = count($this->messages);

        $this->pushAssistant($payload['text']);

        $this->dispatch('milha-scroll-start', index: $firstNewIndex);
    }

    public function isAwaiting(): bool
    {
        return $this->replyKey !== null;
    }

    public function render()
    {
        return view('livewire.milha-vendedora-chat');
    }

    /**
     * Conversa desta sessão no banco (cria na primeira interação real —
     * pageview sozinho não gera linha).
     */
    private function conversation(): MilhaLeadConversation
    {
        $id = session('milha_lp.conversation_id');

        $conversation = $id !== null ? MilhaLeadConversation::find($id) : null;

        if ($conversation === null) {
            $conversation = MilhaLeadConversation::create([
                'session_id' => session()->getId(),
                'ip' => request()->ip(),
                'user_agent' => mb_strimwidth((string) request()->userAgent(), 0, 255, '…'),
            ]);

            // A saudação proativa entra como primeira mensagem do registro.
            $conversation->messages()->create(['role' => 'assistant', 'content' => self::GREETING]);

            session(['milha_lp.conversation_id' => $conversation->getKey()]);
        }

        return $conversation;
    }

    /** Registra fala da assistente na tela e no histórico da sessão. */
    private function pushAssistant(string $content): void
    {
        $this->messages[] = ['role' => 'assistant', 'html' => $this->markdown($content)];

        session([self::SESSION_KEY => [
            ...session(self::SESSION_KEY, []),
            ['role' => 'assistant', 'content' => $content],
        ]]);
    }

    /** Markdown da assistente → HTML seguro (links internos permitidos). */
    private function markdown(string $text): string
    {
        return (string) Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 6,
        ]);
    }
}
