<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
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

class HelpdeskPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('helpdesk')
            ->path('helpdesk')
            ->viteTheme('resources/css/filament/helpdesk/theme.css')
            ->login()
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::Light)
            ->font('Inter')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>'),
            )
            ->discoverResources(in: app_path('Filament/Helpdesk/Resources'), for: 'App\Filament\Helpdesk\Resources')
            ->resources([
                UserResource::class,
                RoleResource::class,
                DepartmentResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Helpdesk/Pages'), for: 'App\Filament\Helpdesk\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Helpdesk/Widgets'), for: 'App\Filament\Helpdesk\Widgets')
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
