<?php

namespace App\Providers\Filament;

use App\Filament\Casual\Pages\Auth\Login;
use App\Filament\Casual\Pages\Auth\Register;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CasualPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $domain = env('CASUAL_DOMAIN');

        return $panel
            ->id('casual')
            ->when($domain, fn (Panel $p) => $p->domain($domain)->path(''))
            ->when(! $domain, fn (Panel $p) => $p->path('casual'))
            ->login(Login::class)
            ->registration(Register::class)
            ->viteTheme('resources/css/filament/casual/theme.css')
            ->defaultThemeMode(ThemeMode::Light)
            ->navigation(false)
            ->topbar(false)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render(
                    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
                     <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                     <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>'
                ),
            )
            ->discoverResources(in: app_path('Filament/Casual/Resources'), for: 'App\Filament\Casual\Resources')
            ->discoverPages(in: app_path('Filament/Casual/Pages'), for: 'App\Filament\Casual\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Casual/Widgets'), for: 'App\Filament\Casual\Widgets')
            ->widgets([])
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
            ]);
    }
}
