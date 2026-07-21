<?php

namespace App\Services\Asaas\Endpoints;

use App\Enums\HttpMethod;

trait AsaasWebhook
{
    public function storeWebhook(
        string $name,
        string $url,
        ?string $email = null,
        ?array $events = null,
        ?bool $enabled = true,
        ?bool $interrupted = false,
        ?string $authToken = null
    ): array {
        $params = [
            'name' => $name,
            'url' => $url,
            'email' => $email,
            'events' => $events,
            'enabled' => $enabled,
            'interrupted' => $interrupted,
            'authToken' => $authToken,
        ];

        return $this->perform(
            method: HttpMethod::POST,
            endpoint: 'webhooks',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function findWebhook(string $id): array
    {
        return $this->perform(
            method: HttpMethod::GET,
            endpoint: "webhooks/{$id}"
        );
    }

    public function updateWebhook(
        string $id,
        ?string $name = null,
        ?string $url = null,
        ?string $email = null,
        ?array $events = null,
        ?bool $enabled = null,
        ?bool $interrupted = null,
        ?string $authToken = null
    ): array {
        $params = [
            'name' => $name,
            'url' => $url,
            'email' => $email,
            'events' => $events,
            'enabled' => $enabled,
            'interrupted' => $interrupted,
            'authToken' => $authToken,
        ];

        return $this->perform(
            method: HttpMethod::PUT,
            endpoint: "webhooks/{$id}",
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }

    public function deleteWebhook(string $id): array
    {
        return $this->perform(
            method: HttpMethod::DELETE,
            endpoint: "webhooks/{$id}"
        );
    }

    public function listWebhooks(?int $limit = null, ?int $offset = null): array
    {
        $params = compact('limit', 'offset');

        return $this->perform(
            method: HttpMethod::GET,
            endpoint: 'webhooks',
            params: array_filter($params, fn ($value) => $value !== null)
        );
    }
}
