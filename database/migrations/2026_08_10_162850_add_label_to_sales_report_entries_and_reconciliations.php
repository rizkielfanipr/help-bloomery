<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales Report input/reconciliation is now split by order-type label
     * (DINE IN / TAKEAWAY), matching the label on the branch's ESB code
     * pairs. Existing rows predate this split, so they're backfilled to
     * TAKEAWAY via the column default (per explicit product decision).
     */
    public function up(): void
    {
        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->string('label', 20)->default('TAKEAWAY')->after('shift_number');
        });

        // Add the new label-aware unique before dropping the old one, since
        // MySQL needs an index covering sales_report_id at all times to
        // satisfy the entries→reports foreign key.
        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->unique(['sales_report_id', 'shift_number', 'payment_method_name', 'label'], 'sre_report_shift_method_label_unique');
        });

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropUnique('sre_report_shift_method_unique');
        });

        Schema::table('sales_report_reconciliations', function (Blueprint $table): void {
            $table->string('label', 20)->default('TAKEAWAY')->after('payment_method_name');
        });

        Schema::table('sales_report_reconciliations', function (Blueprint $table): void {
            $table->unique(['sales_report_id', 'payment_method_name', 'label'], 'src_report_method_label_unique');
        });

        Schema::table('sales_report_reconciliations', function (Blueprint $table): void {
            $table->dropUnique('src_report_method_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales_report_reconciliations', function (Blueprint $table): void {
            $table->unique(['sales_report_id', 'payment_method_name'], 'src_report_method_unique');
        });

        Schema::table('sales_report_reconciliations', function (Blueprint $table): void {
            $table->dropUnique('src_report_method_label_unique');
            $table->dropColumn('label');
        });

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->unique(['sales_report_id', 'shift_number', 'payment_method_name'], 'sre_report_shift_method_unique');
        });

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropUnique('sre_report_shift_method_label_unique');
            $table->dropColumn('label');
        });
    }
};
