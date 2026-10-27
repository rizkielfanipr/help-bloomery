<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * docs/products/daily-briefing/scoring-ui/prd.md §16 (Phase 11). The Employee App's Nilai Briefing
     * page previously had no canAccess() check at all; gating it (and the PDF, which already
     * required this permission) behind `view briefing scores` would otherwise lock out the
     * STORE_STAFF users it exists for — docs/products/daily-briefing/remediation/prd.md §18 already
     * recommended "STORE_STAFF dapat melihat score PDF: Ya, hanya branch sendiri".
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'STORE_STAFF')->where('guard_name', 'web')->first()
            ?->givePermissionTo(Permission::firstOrCreate(['name' => 'view briefing scores', 'guard_name' => 'web']));

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
