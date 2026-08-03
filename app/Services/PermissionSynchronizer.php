<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSynchronizer
{
    private const DEPRECATED_PERMISSIONS = [
        'access admin panel',
        'access helpdesk panel',
        'access driver panel',
        'access technician panel',
        'access casual panel',
        'access employee app',
        'access driver app',
        'access technician app',
        'view tile absensi',
        'view tile driver',
        'view tile teknisi',
        'view tile req teknisi',
        'view tile briefing',
        'view tile sales report',
        'view tile purchasing',
        'view tile desain',
        'view tile erp',
        'view tile stock card',
        'view all branch data',
        'view sales regions',
        'create sales regions',
        'edit sales regions',
        'delete sales regions',
        'view payment method groups',
        'create payment method groups',
        'edit payment method groups',
        'delete payment method groups',
    ];

    /** @return Collection<int, string> */
    public function configuredPermissions(): Collection
    {
        return collect(app(PermissionRegistry::class)->groups())
            ->flatMap(fn (array $resources): array => array_merge(...array_values($resources)))
            ->unique()
            ->values();
    }

    public function sync(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $created = 0;

        $createdPermissions = collect();

        $this->configuredPermissions()->each(function (string $name) use (&$created, $createdPermissions): void {
            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $created++;
                $createdPermissions->push($name);
            }
        });

        if ($createdPermissions->contains(fn (string $name): bool => str_starts_with($name, 'access '))) {
            $this->migrateDefaultAppAccess();
        }

        Permission::where('guard_name', 'web')
            ->whereIn('name', self::DEPRECATED_PERMISSIONS)
            ->delete();

        if ($superAdmin = Role::where('name', 'SUPERADMIN')->where('guard_name', 'web')->first()) {
            $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }

    private function migrateDefaultAppAccess(): void
    {
        foreach (config('default-app-access', []) as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }
    }
}
