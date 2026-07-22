<?php

namespace App\Jobs;

use App\Ai\ChartRegistry;
use App\Ai\Milha;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Approvals\Decision;
use Throwable;

/**
 * Executa um turno da Milha fora do request web: chama a API do Groq e
 * deposita o resultado no cache para o MilhaChat buscar via polling.
 * O delay de 1s no dispatch é a janela de arrependimento do botão stop —
 * o cancelamento é um flag no cache que o job checa antes de chamar a API.
 */
class MilhaPromptJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $timeout = 90;

    public int $tries = 1;

    private const TTL = 600;

    public function __construct(
        public Tenant $tenant,
        public User $user,
        public ?string $conversationId,
        public ?string $prompt,
        /** 'approve' | 'reject' | null (null = prompt de texto) */
        public ?string $decision,
        public string $replyKey,
    ) {}

    public static function cancelKey(string $replyKey): string
    {
        return 'milha:cancel:'.$replyKey;
    }

    public static function replyCacheKey(string $replyKey): string
    {
        return 'milha:reply:'.$replyKey;
    }

    public function handle(): void
    {
        if (Cache::pull(self::cancelKey($this->replyKey)) !== null) {
            Cache::put(self::replyCacheKey($this->replyKey), ['status' => 'cancelled'], self::TTL);

            return;
        }

        try {
            $milha = new Milha($this->tenant, $this->user);

            $milha = $this->conversationId !== null
                ? $milha->continue($this->conversationId, $this->user)
                : $milha->forUser($this->user);

            $response = $milha->prompt(match ($this->decision) {
                'approve' => Decision::approveAll(),
                'reject' => Decision::rejectAll('O usuário recusou a ação. Não execute.'),
                default => (string) $this->prompt,
            });

            Cache::put(self::replyCacheKey($this->replyKey), [
                'status' => 'done',
                'conversationId' => $response->conversationId ?? $this->conversationId,
                'text' => $response->text,
                'charts' => app(ChartRegistry::class)->flush(),
                'pending' => $response->pendingApprovals->map->toArray()->all(),
            ], self::TTL);
        } catch (Throwable $exception) {
            report($exception);

            Cache::put(self::replyCacheKey($this->replyKey), ['status' => 'error'], self::TTL);
        }
    }

    /** Falha dura do job (timeout, worker morto): o chat não pode ficar esperando. */
    public function failed(?Throwable $exception): void
    {
        Cache::put(self::replyCacheKey($this->replyKey), ['status' => 'error'], self::TTL);
    }
}
