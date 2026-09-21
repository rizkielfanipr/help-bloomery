<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view technician monthly maintenance',
        'create technician monthly maintenance',
        'edit technician monthly maintenance',
        'delete technician monthly maintenance',
    ];

    /**
     * These permissions were only created by the seeder, so environments that never
     * re-seeded lack them and technicians get a 403 on the maintenance pages.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'TECHNICIAN')->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Roles may have been adjusted since, so the grant is not reversed.
     */
    public function down(): void
    {
        //
    }
};
