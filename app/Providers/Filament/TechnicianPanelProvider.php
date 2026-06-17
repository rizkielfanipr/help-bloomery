<?php

namespace App\Providers\Filament;

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

class TechnicianPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $domain = env('TECHNICIAN_DOMAIN');

        return $panel
            ->id('technician')
            ->when($domain, fn (Panel $p) => $p->domain($domain)->path(''))
            ->when(! $domain, fn (Panel $p) => $p->path('technician'))
            ->login()
            ->viteTheme('resources/css/filament/technician/theme.css')
            ->navigation(false)
            ->topbar(false)
            ->colors([
                'primary' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Technician/Resources'), for: 'App\Filament\Technician\Resources')
            ->discoverPages(in: app_path('Filament/Technician/Pages'), for: 'App\Filament\Technician\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Technician/Widgets'), for: 'App\Filament\Technician\Widgets')
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
