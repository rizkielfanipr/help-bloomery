<?php

namespace App\Providers;

use App\Http\Middleware\EnsureRole;
use App\Models\HelpdeskFormTemplate;
use App\Observers\HelpdeskFormTemplateObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::aliasMiddleware('role', EnsureRole::class);

        HelpdeskFormTemplate::observe(HelpdeskFormTemplateObserver::class);

        $this->syncPermissions();
    }

    private function syncPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $allPermissions = array_merge(...array_values(config('permissions', [])));
        $cacheKey = 'permissions_synced_'.md5(serialize($allPermissions));

        if (Cache::has($cacheKey)) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        $superAdmin?->syncPermissions(Permission::all());

        Cache::forever($cacheKey, true);
    }
}
