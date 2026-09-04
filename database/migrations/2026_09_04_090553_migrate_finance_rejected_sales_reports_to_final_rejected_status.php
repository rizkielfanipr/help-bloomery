<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sales_reports')
            ->where('status', 'pending_supervisor')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('sales_report_approvals')
                    ->whereColumn('sales_report_approvals.sales_report_id', 'sales_reports.id')
                    ->where('sales_report_approvals.stage', 'finance')
                    ->where('sales_report_approvals.action', 'rejected');
            })
            ->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Final Finance decisions are intentionally not reopened on rollback.
    }
};
