<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/daily-briefing-bundle-approval-prd.md §7.1: one canonical bundle per branch_id + period +
     * period_start, enforced by the database. Safe on legacy data: ungrouped rows keep period_start
     * NULL (NULLs never collide in a unique index), and neither the resolver nor the grouping command
     * ever stamps a period_start onto rows that compete for the same key — they report a conflict
     * instead. Replaces the plain lookup index on the same columns. Guarded so a half-finished run
     * on MySQL (non-transactional DDL) can be re-run either way.
     */
    public function up(): void
    {
        if (! Schema::hasIndex('briefing_records', 'briefing_records_bundle_unique')) {
            Schema::table('briefing_records', function (Blueprint $table) {
                $table->unique(['branch_id', 'period', 'period_start'], 'briefing_records_bundle_unique');
            });
        }

        if (Schema::hasIndex('briefing_records', 'briefing_records_bundle_key_index')) {
            Schema::table('briefing_records', fn (Blueprint $table) => $table->dropIndex('briefing_records_bundle_key_index'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('briefing_records', 'briefing_records_bundle_key_index')) {
            Schema::table('briefing_records', function (Blueprint $table) {
                $table->index(['branch_id', 'period', 'period_start'], 'briefing_records_bundle_key_index');
            });
        }

        if (Schema::hasIndex('briefing_records', 'briefing_records_bundle_unique')) {
            Schema::table('briefing_records', fn (Blueprint $table) => $table->dropUnique('briefing_records_bundle_unique'));
        }
    }
};
