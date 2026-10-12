<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/daily-briefing-remediation-prd.md §11.2/§14 (Phase 6). A rejected submission cannot be
     * edited after its deadline (BriefingSubmissionAction) unless a reviewer reopens it: reopening
     * records who, when, and why, and grants exactly one further edit before the reopen grant is
     * consumed again.
     */
    public function up(): void
    {
        Schema::table('briefing_items', function (Blueprint $table) {
            $table->timestamp('reopened_at')->nullable()->after('reviewed_at');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by');
        });
    }

    public function down(): void
    {
        Schema::table('briefing_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['reopened_at', 'reopen_reason']);
        });
    }
};
