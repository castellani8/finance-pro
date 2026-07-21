<?php

namespace App\Services\Asaas;

use App\Enums\HttpMethod;
use App\Exceptions\AsaasException;
use App\Services\Asaas\Endpoints\AsaasCustomer;
use App\Services\Asaas\Endpoints\AsaasPayment;
use App\Services\Asaas\Endpoints\AsaasSubscription;
use App\Services\Asaas\Endpoints\AsaasWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Asaas
{
    use AsaasCustomer;
    use AsaasPayment;
    use AsaasSubscription;
    use AsaasWebhook;

    protected ?string $baseUrl;

    protected ?array $headers = [];

    public function __construct()
    {
        $this->headers = ['access_token' => config('services.asaas.access_token')];
        $this->baseUrl = config('services.asaas.base_url');
    }

    public function perform(HttpMethod $method, string $endpoint, ?array $params = []): array
    {
        $fullUrl = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

        try {
            $http = Http::withHeaders($this->headers)
                ->acceptJson()
                ->asJson()
                ->baseUrl($this->baseUrl)
                ->{$method->value}(
                    $endpoint,
                    $params
                );
        } catch (\Throwable $e) {
            Log::error('[Asaas] Erro de conexão', [
                'url' => $fullUrl,
                'method' => $method->value,
                'error' => $e->getMessage(),
            ]);

            throw new AsaasException(
                'Erro de conexão com o gateway',
                $e->getCode(),
                $e
            );
        }

        $body = json_decode($http->body(), true, 512);

        if ($http->failed()) {
            Log::error('[Asaas] Requisição falhou', [
                'url' => $fullUrl,
                'method' => $method->value,
                'status' => $http->getStatusCode(),
                'response' => $body,
            ]);

            throw new AsaasException(
                json_encode($body),
                $http->getStatusCode(),
            );
        }

        return [
            'status_code' => $http->getStatusCode(),
            'body' => $body,
        ];
    }

    public function withHeaders(array $headers): static
    {
        $this->headers += $headers;

        return $this;
    }
}
