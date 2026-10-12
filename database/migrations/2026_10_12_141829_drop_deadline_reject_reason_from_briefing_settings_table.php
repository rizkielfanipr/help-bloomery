<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/daily-briefing-remediation-prd.md §11.4 (Phase 7). A missed deadline with nothing
     * submitted is now a derived `missing` status (BriefingStatusService) and never creates a
     * rejected briefing_item, so this reason text — previously shown on a synthetic deadline
     * rejection — no longer has anything to display on. Only the singleton settings row's own
     * column is dropped; no submission/history data is touched.
     */
    public function up(): void
    {
        Schema::table('briefing_settings', function (Blueprint $table) {
            $table->dropColumn('deadline_reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('briefing_settings', function (Blueprint $table) {
            $table->string('deadline_reject_reason')->default('Tidak ada input sebelum deadline.');
        });
    }
};
