<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales Number snapshot for Compliment (docs/sales-report-compliment-void-remediation-prd.md §9.5).
 * Additive and nullable: existing rows keep `null` (shown as legacy) and receive no backfill.
 * No unique constraint until it is confirmed whether one Sales Number may carry several
 * Compliment types (§25).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_report_compliments', function (Blueprint $table): void {
            $table->string('sales_number', 50)->nullable()->after('shift_number');
            $table->string('sales_number_source', 20)->nullable()->after('sales_number');
            $table->string('source_comcode', 50)->nullable()->after('sales_number_source');
            $table->string('source_branch_code', 50)->nullable()->after('source_comcode');
            $table->dateTime('sales_transaction_at')->nullable()->after('source_branch_code');
            $table->index('sales_number');
        });
    }

    public function down(): void
    {
        Schema::table('sales_report_compliments', function (Blueprint $table): void {
            $table->dropIndex(['sales_number']);
            $table->dropColumn(['sales_number', 'sales_number_source', 'source_comcode', 'source_branch_code', 'sales_transaction_at']);
        });
    }
};
