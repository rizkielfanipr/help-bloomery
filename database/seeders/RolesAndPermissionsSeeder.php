<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = array_merge(...array_values(array_map(
            fn (array $resources) => array_merge(...array_values($resources)),
            config('permissions', [])
        )));

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'casual_staff', 'guard_name' => 'web'])
            ->syncPermissions([]);

        Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web'])
            ->syncPermissions([
                'view trips', 'view trip routes', 'view vehicles',
            ]);

        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web'])
            ->syncPermissions([
                'view service requests', 'create service requests', 'edit service requests', 'delete service requests',
            ]);

        Role::firstOrCreate(['name' => 'hr_staff', 'guard_name' => 'web'])
            ->syncPermissions([
                'view casual staff', 'create casual staff', 'edit casual staff', 'delete casual staff',
                'view casual positions', 'create casual positions', 'edit casual positions', 'delete casual positions',
                'view casual openings', 'create casual openings', 'edit casual openings', 'delete casual openings',
                'view clock records', 'create clock records', 'edit clock records', 'delete clock records',
                'view briefing records', 'create briefing records', 'edit briefing records', 'delete briefing records',
                'view briefing items', 'create briefing items', 'edit briefing items', 'delete briefing items',
                'view sales reports',
                'view purchase requests', 'create purchase requests', 'edit purchase requests',
                'view design requests', 'edit design requests',
                'view users',
            ]);

        Role::firstOrCreate(['name' => 'helpdesk_staff', 'guard_name' => 'web'])
            ->syncPermissions([
                'view service requests', 'create service requests', 'edit service requests',
                'view trips', 'view trip routes', 'view vehicles',
                'view briefing records', 'view briefing items',
                'view sales reports',
                'view purchase requests',
                'view erp requests', 'edit erp requests',
            ]);

        Role::firstOrCreate(['name' => 'helpdesk_manager', 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());
    }
}
