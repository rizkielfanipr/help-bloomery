<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Back office requests created before the branch field existed have no branch,
     * so branch-scoped technicians never see them. Fill the branch from the asset,
     * then from the requester's primary branch.
     */
    public function up(): void
    {
        DB::table('service_requests')
            ->whereNull('branch_id')
            ->whereNotNull('asset_id')
            ->update(['branch_id' => DB::raw('(select branch_id from assets where assets.id = service_requests.asset_id)')]);

        DB::table('service_requests')
            ->whereNull('branch_id')
            ->whereNotNull('scheduled_by')
            ->update(['branch_id' => DB::raw('coalesce(
                (select branch_id from user_branches where user_branches.user_id = service_requests.scheduled_by and user_branches.is_primary = 1 limit 1),
                (select branch_id from users where users.id = service_requests.scheduled_by)
            )')]);
    }

    /**
     * The backfill cannot be told apart from branches set by users, so it is not reversed.
     */
    public function down(): void
    {
        //
    }
};
