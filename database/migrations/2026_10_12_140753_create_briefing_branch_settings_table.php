<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/products/daily-briefing/remediation/prd.md §8. Explicit branch participation: a branch only
     * takes part in Daily Briefing when it has an enabled row here covering the date being
     * evaluated. No row is seeded by this migration — the participant branch list and start dates
     * are a business decision made through the Settings page after deploy, not inferred from
     * existing data (§8.3 "migration tidak boleh otomatis mengaktifkan seluruh branch").
     *
     * Multiple rows per branch are allowed so a branch's participation history (enable, disable,
     * re-enable) is preserved; overlap between a branch's own ranges is prevented at the
     * application layer (BriefingAssignmentService / the settings action), not by a DB constraint,
     * since MySQL has no native exclusion constraint for date ranges.
     */
    public function up(): void
    {
        Schema::create('briefing_branch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'is_enabled', 'started_at', 'ended_at'], 'briefing_branch_settings_lookup_index');
        });
    }

    /**
     * Safe to drop without a data-preserving down() for now: no row is seeded by up(), so until a
     * business decision is recorded through the Settings page there is no participation data in
     * this table that a rollback would need to keep.
     */
    public function down(): void
    {
        Schema::dropIfExists('briefing_branch_settings');
    }
};
