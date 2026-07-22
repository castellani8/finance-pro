<?php

namespace App\Jobs;

use App\Ai\MilhaVendedora;
use App\Models\MilhaLeadConversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Turno da Milha vendedora (landing page) na fila. O histórico do visitante
 * viaja no próprio job e a resposta volta pelo cache (mesmo padrão do
 * painel). A resposta e os tokens são gravados aqui no banco — assim a
 * métrica não depende do visitante continuar na página para o poll coletar.
 */
class MilhaVendedoraJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    private const TTL = 600;

    /** @param array<int, array{role: string, content: string}> $history */
    public function __construct(
        public array $history,
        public string $prompt,
        public string $replyKey,
        public ?int $conversationDbId = null,
    ) {}

    public static function cancelKey(string $replyKey): string
    {
        return 'milha-lp:cancel:'.$replyKey;
    }

    public static function replyCacheKey(string $replyKey): string
    {
        return 'milha-lp:reply:'.$replyKey;
    }

    public function handle(): void
    {
        if (Cache::pull(self::cancelKey($this->replyKey)) !== null) {
            Cache::put(self::replyCacheKey($this->replyKey), ['status' => 'cancelled'], self::TTL);

            return;
        }

        try {
            $response = (new MilhaVendedora($this->history))->prompt($this->prompt);

            $this->trackAssistantMessage($response->text, $response->usage->promptTokens, $response->usage->completionTokens);

            Cache::put(self::replyCacheKey($this->replyKey), [
                'status' => 'done',
                'text' => $response->text,
            ], self::TTL);
        } catch (Throwable $exception) {
            report($exception);

            Cache::put(self::replyCacheKey($this->replyKey), ['status' => 'error'], self::TTL);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::put(self::replyCacheKey($this->replyKey), ['status' => 'error'], self::TTL);
    }

    private function trackAssistantMessage(string $text, int $promptTokens, int $completionTokens): void
    {
        $conversation = $this->conversationDbId !== null
            ? MilhaLeadConversation::find($this->conversationDbId)
            : null;

        if ($conversation === null) {
            return;
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $text,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ]);

        $conversation->increment('prompt_tokens', $promptTokens);
        $conversation->increment('completion_tokens', $completionTokens);
    }
}
