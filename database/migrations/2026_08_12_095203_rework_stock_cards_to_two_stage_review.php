<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock Card now mirrors the Sales Report two-stage review flow:
     * Draft -> Pending Supervisor -> Pending Finance -> Completed, with
     * Finance able to bounce a card back to Supervisor. The single-stage
     * reviewed_by/reviewed_at/review_note columns are replaced by separate
     * supervisor/finance pairs, and system_fetched_at tracks whether ESB
     * usage data has been pulled for comparison yet (gates approval).
     */
    public function up(): void
    {
        Schema::table('stock_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_note']);

            $table->foreignId('supervisor_reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('supervisor_reviewed_by');
            $table->text('supervisor_note')->nullable()->after('supervisor_reviewed_at');
            $table->foreignId('finance_reviewed_by')->nullable()->after('supervisor_note')->constrained('users')->nullOnDelete();
            $table->timestamp('finance_reviewed_at')->nullable()->after('finance_reviewed_by');
            $table->text('finance_note')->nullable()->after('finance_reviewed_at');
            $table->timestamp('system_fetched_at')->nullable()->after('finance_note');
        });

        // Best-effort remap of the old single-stage statuses (pre-production data only).
        DB::table('stock_cards')->where('status', 'approved')->update(['status' => 'completed']);
        DB::table('stock_cards')->where('status', 'revision_requested')->update(['status' => 'pending_supervisor']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_reviewed_by');
            $table->dropConstrainedForeignId('finance_reviewed_by');
            $table->dropColumn(['supervisor_reviewed_at', 'supervisor_note', 'finance_reviewed_at', 'finance_note', 'system_fetched_at']);

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
        });
    }
};
