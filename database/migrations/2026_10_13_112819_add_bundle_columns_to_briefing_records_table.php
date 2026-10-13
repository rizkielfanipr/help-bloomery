<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * docs/daily-briefing-bundle-approval-prd.md §7.1/§10.1: a briefing_records row becomes the
     * canonical bundle for branch_id + period + period_start. Every column is nullable because
     * legacy rows only get them through the grouping command, never through this migration; a
     * null status means "legacy, not yet grouped". The unique bundle key is deliberately an
     * ordinary index here and only becomes unique after duplicate reconciliation (§15.6).
     */
    public function up(): void
    {
        Schema::table('briefing_records', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('record_date');
            $table->date('period_end')->nullable()->after('period_start');
            $table->string('status')->nullable()->after('period_end');
            $table->timestamp('completed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->unsignedTinyInteger('scoring_method_version')->nullable()->after('review_notes');

            $table->index(['branch_id', 'period', 'period_start'], 'briefing_records_bundle_key_index');
            $table->index(['branch_id', 'status', 'period_start'], 'briefing_records_bundle_monitoring_index');
            $table->index(['status', 'completed_at'], 'briefing_records_bundle_review_queue_index');
        });
    }

    /**
     * MySQL silently drops the implicit index behind the branch_id foreign key once another index
     * starts with branch_id, so that single-column index is restored before the composite ones go
     * (otherwise MySQL refuses: "needed in a foreign key constraint"). MySQL DDL is not
     * transactional, so every step is guarded to make a half-finished rollback safe to re-run.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('briefing_records', 'briefing_records_branch_id_foreign')) {
            Schema::table('briefing_records', function (Blueprint $table) {
                $table->index('branch_id', 'briefing_records_branch_id_foreign');
            });
        }

        foreach ([
            'briefing_records_bundle_key_index',
            'briefing_records_bundle_monitoring_index',
            'briefing_records_bundle_review_queue_index',
        ] as $index) {
            if (Schema::hasIndex('briefing_records', $index)) {
                Schema::table('briefing_records', fn (Blueprint $table) => $table->dropIndex($index));
            }
        }

        if (Schema::hasColumn('briefing_records', 'reviewed_by')) {
            Schema::table('briefing_records', fn (Blueprint $table) => $table->dropConstrainedForeignId('reviewed_by'));
        }

        $columns = array_values(array_filter(
            ['period_start', 'period_end', 'status', 'completed_at', 'reviewed_at', 'review_notes', 'scoring_method_version'],
            fn (string $column): bool => Schema::hasColumn('briefing_records', $column),
        ));

        if ($columns !== []) {
            Schema::table('briefing_records', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
