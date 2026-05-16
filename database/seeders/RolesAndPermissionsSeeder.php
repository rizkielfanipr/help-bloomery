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

        $permissions = [
            // User management
            'view users', 'create users', 'edit users', 'delete users',
            // Helpdesk
            'view tickets', 'create tickets', 'edit tickets', 'delete tickets',
            'assign tickets', 'close tickets', 'view internal comments',
            'view helpdesk reports',
            // Driver logbook
            'view driver logs', 'create driver logs', 'edit driver logs',
            'approve driver logs', 'view driver reports',
            // Technician logbook
            'view technician logs', 'create technician logs', 'edit technician logs',
            'approve technician logs', 'view technician reports',
            // Attendance
            'view attendances', 'create attendances', 'edit attendances',
            'manage attendance periods', 'view attendance reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        Role::firstOrCreate(['name' => 'super_admin'])
            ->givePermissionTo(Permission::all());

        Role::firstOrCreate(['name' => 'helpdesk_manager'])
            ->givePermissionTo([
                'view users',
                'view tickets', 'create tickets', 'edit tickets', 'assign tickets', 'close tickets', 'view internal comments',
                'view helpdesk reports',
                'view driver logs', 'approve driver logs', 'view driver reports',
                'view technician logs', 'approve technician logs', 'view technician reports',
                'view attendances', 'view attendance reports',
            ]);

        Role::firstOrCreate(['name' => 'helpdesk_staff'])
            ->givePermissionTo([
                'view tickets', 'create tickets', 'edit tickets', 'assign tickets', 'close tickets', 'view internal comments',
            ]);

        Role::firstOrCreate(['name' => 'driver'])
            ->givePermissionTo(['view driver logs', 'create driver logs', 'edit driver logs']);

        Role::firstOrCreate(['name' => 'technician'])
            ->givePermissionTo(['view technician logs', 'create technician logs', 'edit technician logs']);

        Role::firstOrCreate(['name' => 'hr_staff'])
            ->givePermissionTo([
                'view attendances', 'create attendances', 'edit attendances',
                'manage attendance periods', 'view attendance reports',
            ]);

        Role::firstOrCreate(['name' => 'casual_staff'])
            ->givePermissionTo(['view attendances']);
    }
}
