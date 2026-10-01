<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const SUBMITTER_PERMISSIONS = [
        'create customer complaints',
    ];

    private const REVIEWER_PERMISSIONS = [
        'view any customer complaints',
        'view customer complaints',
        'update customer complaints',
    ];

    /**
     * New permissions only exist after a fresh seed; this grants them on an already-deployed
     * production database (docs/customer-complaints-prd.md §12). STORE_STAFF/SUPERVISOR_STORE can
     * submit a complaint; SUPERVISOR_STORE additionally reviews complaints within their branch
     * scope (CustomerComplaintPolicy). `delete customer complaints` is intentionally left to
     * SUPERADMIN only (which already holds every permission).
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...self::SUBMITTER_PERMISSIONS, ...self::REVIEWER_PERMISSIONS, 'delete customer complaints'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'STORE_STAFF')->where('guard_name', 'web')->first()?->givePermissionTo(self::SUBMITTER_PERMISSIONS);

        Role::where('name', 'SUPERVISOR_STORE')->where('guard_name', 'web')->first()?->givePermissionTo([
            ...self::SUBMITTER_PERMISSIONS,
            ...self::REVIEWER_PERMISSIONS,
        ]);

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
