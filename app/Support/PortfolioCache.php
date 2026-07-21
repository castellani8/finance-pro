<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache por tenant para os agregados da carteira (gráficos e estatísticas).
 *
 * Usa o padrão de versão: cada tenant tem um número de versão que entra na
 * chave; invalidar tudo de um tenant é só incrementar a versão (funciona em
 * qualquer driver de cache, sem depender de tags).
 */
class PortfolioCache
{
    public static function remember(int $tenantId, string $key, Closure $callback, int $ttlMinutes = 15): mixed
    {
        $version = Cache::get(self::versionKey($tenantId), 1);

        return Cache::remember(
            "portfolio.{$tenantId}.v{$version}.{$key}",
            now()->addMinutes($ttlMinutes),
            $callback,
        );
    }

    /** Invalida todos os agregados em cache do tenant. */
    public static function bump(int $tenantId): void
    {
        Cache::forever(self::versionKey($tenantId), Cache::get(self::versionKey($tenantId), 1) + 1);
    }

    private static function versionKey(int $tenantId): string
    {
        return "portfolio.version.{$tenantId}";
    }
}
