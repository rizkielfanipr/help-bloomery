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

        Role::firstOrCreate(['name' => 'SUPERADMIN', 'guard_name' => 'web'])
            ->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'CASUAL_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([]);

        Role::firstOrCreate(['name' => 'HRD_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([
                'view casual staff', 'create casual staff', 'edit casual staff', 'delete casual staff',
                'view casual positions', 'create casual positions', 'edit casual positions', 'delete casual positions',
                'view casual openings', 'create casual openings', 'edit casual openings', 'delete casual openings',
                'view clock records', 'create clock records', 'edit clock records', 'delete clock records',
                'view briefing records', 'create briefing records', 'edit briefing records', 'delete briefing records',
                'view briefing items', 'create briefing items', 'edit briefing items', 'delete briefing items',
                'view briefing scores', 'create briefing scores', 'edit briefing scores', 'delete briefing scores',
                'view sales reports',
                'view purchase requests', 'create purchase requests', 'edit purchase requests',
                'view design requests', 'edit design requests',
                'view users',
                'view brands', 'create brands', 'edit brands', 'delete brands',
                'view branches', 'create branches', 'edit branches', 'delete branches',
                'view payment method groups', 'create payment method groups', 'edit payment method groups', 'delete payment method groups',
                // tiles
                'view tile req teknisi',
                'view tile briefing',
                'view tile sales report',
                'view tile purchasing',
                'view tile desain',
                'view tile erp',
                // analytics
                'view sales information',
                'view promotion information',
            ]);

        Role::firstOrCreate(['name' => 'STORE_STAFF', 'guard_name' => 'web'])
            ->syncPermissions([
                'view service requests', 'create service requests',
                'view sales reports',
                'view purchase requests', 'create purchase requests',
                'view design requests', 'create design requests',
                'view erp requests', 'create erp requests',
                // tiles
                'view tile req teknisi',
                'view tile briefing',
                'view tile sales report',
                'view tile purchasing',
                'view tile desain',
                'view tile erp',
            ]);

        Role::firstOrCreate(['name' => 'DRIVER', 'guard_name' => 'web'])
            ->syncPermissions([
                'view trips', 'view trip routes', 'view vehicles',
            ]);

        Role::firstOrCreate(['name' => 'TECHNICIAN', 'guard_name' => 'web'])
            ->syncPermissions([
                'view service requests', 'create service requests', 'edit service requests', 'delete service requests',
                // tiles
                'view tile teknisi',
                'view tile req teknisi',
            ]);

        Role::firstOrCreate(['name' => 'SUPERVISOR_STORE', 'guard_name' => 'web'])
            ->syncPermissions([
                'view briefing items', 'edit briefing items',
            ]);

        // Remove any role not in the new set
        $newRoles = ['SUPERADMIN', 'CASUAL_STAFF', 'HRD_STAFF', 'STORE_STAFF', 'DRIVER', 'TECHNICIAN', 'SUPERVISOR_STORE'];
        Role::whereNotIn('name', $newRoles)->delete();

        $this->command->info('Roles seeded: SUPERADMIN, CASUAL_STAFF, HRD_STAFF, STORE_STAFF, DRIVER, TECHNICIAN, SUPERVISOR_STORE');
    }
}
