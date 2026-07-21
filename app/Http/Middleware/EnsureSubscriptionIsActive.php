<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Assinatura;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portão de acesso do painel: quem perdeu o acesso (trial vencido ou
 * assinatura expirada) só navega até a página de assinatura, o perfil e o
 * logout — todo o resto redireciona para regularizar.
 */
class EnsureSubscriptionIsActive
{
    /** Trechos de nome de rota que continuam acessíveis sem assinatura. */
    private const ALLOWED_ROUTE_FRAGMENTS = [
        'assinatura',
        'logout',
        'profile',
        'tenant.registration',
        'email-verification',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasPanelAccess()) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        foreach (self::ALLOWED_ROUTE_FRAGMENTS as $fragment) {
            if (str_contains($routeName, $fragment)) {
                return $next($request);
            }
        }

        $tenant = $user->tenants->first();

        if ($tenant === null) {
            return $next($request);
        }

        return redirect(Assinatura::getUrl(tenant: $tenant));
    }
}
