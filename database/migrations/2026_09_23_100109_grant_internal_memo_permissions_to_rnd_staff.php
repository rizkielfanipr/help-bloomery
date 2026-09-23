<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view any rnd internal memo',
        'view rnd internal memo',
        'create rnd internal memo',
        'update rnd internal memo',
        'sync rnd internal memo',
        'finalize rnd internal memo',
        'create rnd internal memo revision',
        'generate rnd internal memo pdf',
        'download rnd internal memo pdf',
        'archive rnd internal memo',
        'delete rnd internal memo',
        'manage rnd product shelf life',
    ];

    private const RND_OPERATOR_PERMISSIONS = [
        'view any rnd internal memo',
        'view rnd internal memo',
        'create rnd internal memo',
        'update rnd internal memo',
        'sync rnd internal memo',
        'download rnd internal memo pdf',
        'delete rnd internal memo',
        'manage rnd product shelf life',
    ];

    /**
     * These permissions were only created by the seeder, so environments that never re-seeded
     * lack them. RND_STAFF gets the R&D Operator subset (docs/rnd-internal-memo-prd.md §5.1);
     * finalize/archive/revision/generate PDF stay unassigned by default because the PRD defines
     * no hardcoded reviewer/manager role — an administrator grants those via Role & Permission.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'RND_STAFF')->where('guard_name', 'web')->first()?->givePermissionTo(self::RND_OPERATOR_PERMISSIONS);

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
