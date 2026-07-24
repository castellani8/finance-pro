<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Register;
use App\Filament\Pages\Tenancy\RegisterTenant;
use App\Http\Middleware\EnsureSubscriptionIsActive;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            // isSimple: false renderiza o perfil como página completa do
            // painel (com sidebar), em vez do modal simples padrão.
            ->profile(EditProfile::class, isSimple: false)
            ->databaseNotifications()
            ->brandName('Milia Invest')
            ->brandLogo(asset('images/logo.svg'))
            ->darkModeBrandLogo(asset('images/logo-dark.svg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/favicon.svg'))
            ->colors([
                'primary' => Color::hex('#D4AF37'),
                'gray' => Color::Zinc,
            ])
            // Ordem dos grupos na barra lateral (Dashboard fica solto no topo).
            ->navigationGroups([
                'Carteira',
                'Planejamento',
                'Relatórios',
                'Minha conta',
            ])
            ->tenant(Tenant::class)
            ->tenantRegistration(RegisterTenant::class)
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): View => view('filament.footer-disclaimer'),
            )
            // Milha, a assistente de IA: balão flutuante em todas as telas
            // autenticadas com tenant resolvido.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() && Filament::getTenant() !== null
                    ? Blade::render('@livewire(\'milha-chat\', [\'tenantId\' => $tenantId])', [
                        'tenantId' => Filament::getTenant()->getKey(),
                    ])
                    : '',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // O Dashboard custom (com filtro por empresa) é descoberto em
            // App\Filament\Pages e substitui o Dashboard padrão do Filament.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->spa()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureSubscriptionIsActive::class,
            ]);
    }
}
