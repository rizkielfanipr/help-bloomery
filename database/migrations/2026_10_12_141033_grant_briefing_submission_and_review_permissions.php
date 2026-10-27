<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * docs/products/daily-briefing/remediation/prd.md §10/§12.3. Submission and review were previously
     * gated by page access (`access employee app briefing`) and by `edit briefing items`/
     * `review sales reports as supervisor` respectively — neither expresses the actual business
     * rule, and the latter couples Briefing review to an unrelated Sales Report permission. These
     * new permissions are granted on top of the existing ones (nothing already granted is
     * revoked) so this is safe to run on an already-deployed database.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'submit daily briefing', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'review briefing items', 'guard_name' => 'web']);

        Role::where('name', 'STORE_STAFF')->where('guard_name', 'web')->first()
            ?->givePermissionTo('submit daily briefing');

        Role::where('name', 'HRD_STAFF')->where('guard_name', 'web')->first()
            ?->givePermissionTo('review briefing items');

        Role::where('name', 'SUPERVISOR_STORE')->where('guard_name', 'web')->first()
            ?->givePermissionTo('review briefing items');

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
