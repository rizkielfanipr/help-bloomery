<?php

namespace App\Providers;

use App\Filament\Helpdesk\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Http\Middleware\EnsureRole;
use App\Models\User;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->hasRole('SUPERADMIN') ? true : null;
        });

        Route::aliasMiddleware('role', EnsureRole::class);

        FilamentView::registerRenderHook(
            TablesRenderHook::HEADER_CELL,
            fn (array $data) => view('filament.helpdesk.purchase-requests.table-header-cell', $data),
            scopes: ListPurchaseRequests::class,
        );
    }
}
