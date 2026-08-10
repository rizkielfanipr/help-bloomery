<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collapses the one-row-per-shift `sales_reports` design into one row per
     * branch+date, moving per-shift detail onto new child tables:
     *  - sales_report_shift_submissions: when each shift was submitted, by whom.
     *  - sales_report_reconciliations: the day-level (shift 1 + shift 2 combined)
     *    comparison against ESB, replacing the settlement/MDR columns that used
     *    to live on sales_report_entries (which is now pure per-shift staff input).
     */
    public function up(): void
    {
        $this->archiveBeforeRestructure();

        Schema::create('sales_report_shift_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('shift_number');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['sales_report_id', 'shift_number'], 'srss_report_shift_unique');
        });

        Schema::create('sales_report_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method_name', 100);
            $table->decimal('reported_store_amount', 15, 2)->default(0);
            $table->decimal('store_amount', 15, 2)->default(0);
            $table->decimal('system_amount', 15, 2)->nullable();
            $table->decimal('settlement_amount', 15, 2)->nullable();
            $table->decimal('mdr_percentage', 8, 4)->nullable();
            $table->decimal('mdr_amount', 15, 2)->nullable();
            $table->decimal('expected_settlement_amount', 15, 2)->nullable();
            $table->decimal('settlement_difference', 15, 2)->nullable();
            $table->string('reconciliation_status', 30)->nullable();
            $table->text('supervisor_notes')->nullable();
            $table->text('finance_note')->nullable();
            $table->timestamp('system_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['sales_report_id', 'payment_method_name'], 'src_report_method_unique');
        });

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->unsignedTinyInteger('shift_number')->nullable()->after('sales_report_id');
        });

        Schema::table('sales_report_employees', function (Blueprint $table): void {
            $table->unsignedTinyInteger('shift_number')->nullable()->after('sales_report_id');
        });

        // Backfill shift_number on entries and employees from the parent
        // report's (soon to be dropped) shift_number, before reports merge.
        // Done row-by-row in PHP (not a JOIN UPDATE) so this also runs on
        // sqlite, which the test suite uses.
        DB::table('sales_reports')->select('id', 'shift_number')->orderBy('id')->chunk(200, function ($reports): void {
            foreach ($reports as $report) {
                DB::table('sales_report_entries')->where('sales_report_id', $report->id)->update(['shift_number' => $report->shift_number]);
                DB::table('sales_report_employees')->where('sales_report_id', $report->id)->update(['shift_number' => $report->shift_number]);
            }
        });

        // mergeShiftReports() below re-points a whole shift's entries onto the
        // canonical report via a bulk `sales_report_id = X` update. The OLD
        // unique(sales_report_id, payment_method_name) constraint would reject
        // that update outright, since every shift naturally repeats the same
        // payment method names (CASH, QRIS, ...) — so it must be swapped for
        // the shift-aware constraint BEFORE the merge runs, not after. Add the
        // new one first so an index covering sales_report_id (required by the
        // entries→reports foreign key) is never momentarily absent.
        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->unique(['sales_report_id', 'shift_number', 'payment_method_name'], 'sre_report_shift_method_unique');
        });

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropUnique(['sales_report_id', 'payment_method_name']);
        });

        // sales_report_esb_transactions has its own unique(sales_report_id,
        // sales_num) that the same bulk re-point could collide on in theory.
        // This table is no longer written to by the new flow, so the
        // constraint is dropped outright rather than replaced — but a plain
        // index on sales_report_id must be added first, since that unique
        // index is currently the only thing satisfying its FK to sales_reports.
        Schema::table('sales_report_esb_transactions', function (Blueprint $table): void {
            $table->index('sales_report_id', 'sret_report_id_index');
        });

        Schema::table('sales_report_esb_transactions', function (Blueprint $table): void {
            $table->dropUnique(['sales_report_id', 'sales_num']);
        });

        $this->mergeShiftReports();
        $this->backfillReconciliationsFromEntries();

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropColumn([
                'sales_system_amount', 'original_sales_store_amount', 'original_notes',
                'settlement_amount', 'mdr_percentage', 'mdr_amount',
                'expected_settlement_amount', 'settlement_difference',
                'reconciliation_status', 'finance_note',
            ]);
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropUnique(['branch_id', 'report_date', 'shift_number']);
        });

        // submitted_by is no longer meaningful on the merged report: each
        // shift now records its own submitter on sales_report_shift_submissions.
        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropForeign(['submitted_by']);
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropColumn(['shift_number', 'shift_started_at', 'shift_ended_at', 'rejection_reason', 'submitted_by']);
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->unique(['branch_id', 'report_date']);
        });
    }

    /**
     * Safety net for production: before any column is dropped or any row is
     * merged/deleted, snapshot the tables that lose data in this migration
     * (sales_reports, sales_report_entries, branches) into plain archive
     * tables — exact copies, including the columns about to be dropped.
     * Nothing is actually destroyed; the old shape stays queryable forever
     * via these tables. MySQL only (sqlite, used in tests, doesn't support
     * `CREATE TABLE ... LIKE` and doesn't need this).
     */
    private function archiveBeforeRestructure(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['sales_reports', 'sales_report_entries', 'branches'] as $table) {
            $archiveTable = $table.'_archive_before_shift_merge';

            if (Schema::hasTable($archiveTable)) {
                continue;
            }

            DB::statement("CREATE TABLE `{$archiveTable}` LIKE `{$table}`");
            DB::statement("INSERT INTO `{$archiveTable}` SELECT * FROM `{$table}`");
        }
    }

    /**
     * Groups existing sales_reports rows (currently one per shift) by
     * branch+date, keeps the earliest shift's row as the canonical parent,
     * moves every child record from the other shift row(s) onto it, records
     * a shift_submissions row per original shift, then deletes the now-empty
     * duplicate parent rows.
     */
    private function mergeShiftReports(): void
    {
        $statusRank = [
            'draft' => 0,
            'pending_supervisor' => 1,
            'rejected_by_supervisor' => 1,
            'pending_finance' => 2,
            'rejected_by_finance' => 2,
            'completed' => 3,
        ];

        $groups = DB::table('sales_reports')->orderBy('id')->get()
            ->groupBy(fn (object $r): string => $r->branch_id.'|'.$r->report_date);

        foreach ($groups as $rows) {
            $rows = $rows->sortBy('shift_number')->values();
            $canonical = $rows->first();

            foreach ($rows as $row) {
                DB::table('sales_report_shift_submissions')->insert([
                    'sales_report_id' => $canonical->id,
                    'shift_number' => $row->shift_number,
                    'submitted_by' => $row->submitted_by,
                    'submitted_at' => $row->submitted_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($row->id === $canonical->id) {
                    continue;
                }

                foreach (['sales_report_entries', 'sales_report_esb_transactions', 'sales_report_approvals', 'sales_report_employees'] as $table) {
                    DB::table($table)->where('sales_report_id', $row->id)->update(['sales_report_id' => $canonical->id]);
                }
            }

            $mostAdvanced = $rows->sortByDesc(fn (object $r): int => $statusRank[$r->status] ?? 0)->first();
            $finalStatus = in_array($mostAdvanced->status, ['rejected_by_supervisor', 'rejected_by_finance'], true)
                ? 'pending_supervisor'
                : $mostAdvanced->status;

            DB::table('sales_reports')->where('id', $canonical->id)->update([
                'status' => $finalStatus,
                'submitted_at' => $rows->pluck('submitted_at')->filter()->max(),
            ]);

            $duplicateIds = $rows->pluck('id')->reject(fn ($id) => $id === $canonical->id);
            if ($duplicateIds->isNotEmpty()) {
                DB::table('sales_reports')->whereIn('id', $duplicateIds)->delete();
            }
        }
    }

    /**
     * Collapses the old per-entry system/settlement/MDR fields (which existed
     * once per shift per payment method) into one combined reconciliation row
     * per payment method per report, summing store/system/settlement across
     * shifts and recomputing MDR/expected/difference from those combined
     * totals rather than trying to merge the old per-shift percentages.
     */
    private function backfillReconciliationsFromEntries(): void
    {
        $entries = DB::table('sales_report_entries')->get([
            'sales_report_id', 'payment_method_name', 'sales_system_amount',
            'sales_store_amount', 'settlement_amount', 'finance_note', 'notes',
        ]);

        $groups = $entries->groupBy(fn (object $e): string => $e->sales_report_id.'|'.$e->payment_method_name);

        foreach ($groups as $group) {
            $first = $group->first();
            $systemTotal = round((float) $group->sum('sales_system_amount'), 2);
            $storeTotal = round((float) $group->sum('sales_store_amount'), 2);
            $hasSettlement = $group->contains(fn (object $e): bool => $e->settlement_amount !== null);
            $settlementTotal = $hasSettlement ? round((float) $group->sum('settlement_amount'), 2) : null;

            $mdrAmount = $settlementTotal !== null ? max(0, round($systemTotal - $settlementTotal, 2)) : null;
            $mdrPercentage = $mdrAmount !== null && $systemTotal > 0 ? round(($mdrAmount / $systemTotal) * 100, 4) : null;
            $expected = $mdrAmount !== null ? round($systemTotal - $mdrAmount, 2) : null;
            $difference = $settlementTotal !== null && $expected !== null ? round($settlementTotal - $expected, 2) : null;
            $status = match (true) {
                $difference === null => null,
                abs($difference) <= 100.0 => 'matched',
                $difference < 0 => 'under',
                default => 'over',
            };

            DB::table('sales_report_reconciliations')->insert([
                'sales_report_id' => $first->sales_report_id,
                'payment_method_name' => $first->payment_method_name,
                'reported_store_amount' => $storeTotal,
                'store_amount' => $storeTotal,
                'system_amount' => $systemTotal,
                'settlement_amount' => $settlementTotal,
                'mdr_percentage' => $mdrPercentage,
                'mdr_amount' => $mdrAmount,
                'expected_settlement_amount' => $expected,
                'settlement_difference' => $difference,
                'reconciliation_status' => $status,
                'supervisor_notes' => $group->pluck('notes')->filter()->unique()->join(' | ') ?: null,
                'finance_note' => $group->pluck('finance_note')->filter()->unique()->join(' | ') ?: null,
                'system_fetched_at' => $systemTotal > 0 ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // Note: *_archive_before_shift_merge tables created by archiveBeforeRestructure()
    // are intentionally NOT dropped here — they're a permanent record of the
    // pre-migration data and should be removed manually, if ever, not by rollback.
    public function down(): void
    {
        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropUnique(['branch_id', 'report_date']);
            $table->unsignedTinyInteger('shift_number')->default(1);
            $table->dateTime('shift_started_at')->nullable();
            $table->dateTime('shift_ended_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->decimal('sales_system_amount', 15, 2)->default(0);
            $table->decimal('original_sales_store_amount', 15, 2)->nullable();
            $table->text('original_notes')->nullable();
            $table->decimal('settlement_amount', 15, 2)->nullable();
            $table->decimal('mdr_percentage', 8, 4)->nullable();
            $table->decimal('mdr_amount', 15, 2)->nullable();
            $table->decimal('expected_settlement_amount', 15, 2)->nullable();
            $table->decimal('settlement_difference', 15, 2)->nullable();
            $table->string('reconciliation_status', 30)->nullable();
            $table->text('finance_note')->nullable();
            $table->unique(['sales_report_id', 'payment_method_name']);
        });

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropUnique('sre_report_shift_method_unique');
            $table->dropColumn('shift_number');
        });

        Schema::table('sales_report_esb_transactions', function (Blueprint $table): void {
            $table->unique(['sales_report_id', 'sales_num']);
            $table->dropIndex('sret_report_id_index');
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->unique(['branch_id', 'report_date', 'shift_number']);
        });

        Schema::dropIfExists('sales_report_reconciliations');
        Schema::dropIfExists('sales_report_shift_submissions');
    }
};
