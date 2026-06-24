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

        Role::firstOrCreate(['name' => 'casual_staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'hr_staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'helpdesk_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'helpdesk_staff', 'guard_name' => 'web']);
    }
}
