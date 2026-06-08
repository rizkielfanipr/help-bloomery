<?php

namespace App\Providers\Filament;

use App\Filament\Casual\Pages\Auth\Register;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->login()
            ->registration(Register::class)
            ->viteTheme('resources/css/filament/casual/theme.css')
            ->navigation(false)
            ->topbar(false)
            ->colors([
                'primary' => Color::Purple,
            ])
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
