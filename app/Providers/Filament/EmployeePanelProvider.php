<?php

namespace App\Providers\Filament;

use App\Filament\Employee\Pages\Auth\Login;
use App\Filament\Employee\Pages\Auth\Register;
use App\Filament\Employee\Pages\LauncherPage;
use App\Http\Middleware\EnsureEmployeeAppFeaturePermission;
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
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class EmployeePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $domain = config('app.domain');

        return $panel
            ->id('employee')
            ->when($domain, fn (Panel $p) => $p->domain($domain)->path(''))
            ->when(! $domain, fn (Panel $p) => $p->path('casual'))
            ->login(Login::class)
            ->registration(Register::class)
            ->homeUrl(fn (): string => route('filament.employee.home'))
            ->authenticatedRoutes(function (): void {
                Route::get('/', LauncherPage::class)->name('home');
            })
            ->viteTheme('resources/css/filament/employee/theme.css')
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
            ->discoverResources(in: app_path('Filament/Employee/Modules'), for: 'App\Filament\Employee\Modules')
            ->discoverPages(in: app_path('Filament/Employee/Modules'), for: 'App\Filament\Employee\Modules')
            ->discoverWidgets(in: app_path('Filament/Employee/Modules'), for: 'App\Filament\Employee\Modules')
            ->discoverPages(in: app_path('Filament/Employee/Pages'), for: 'App\Filament\Employee\Pages')
            ->pages([])
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
                EnsureEmployeeAppFeaturePermission::class,
            ]);
    }
}
