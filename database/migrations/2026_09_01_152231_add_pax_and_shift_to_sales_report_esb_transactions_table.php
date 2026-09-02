<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('sales_report_esb_transactions', 'shift_number')) {
            return;
        }

        Schema::table('sales_report_esb_transactions', function (Blueprint $table) {
            $table->unsignedTinyInteger('shift_number')->nullable()->after('sales_report_id');
            $table->string('source_branch_code', 50)->nullable()->after('shift_number');
            $table->string('source_comcode', 50)->nullable()->after('source_branch_code');
            $table->unsignedInteger('pax_total')->default(0)->after('payment_total');
            $table->decimal('revenue_total', 18, 2)->default(0)->after('pax_total');
            $table->index(['sales_report_id', 'shift_number'], 'sales_tx_report_shift_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_report_esb_transactions', function (Blueprint $table) {
            $table->dropIndex('sales_tx_report_shift_idx');
            $table->dropColumn([
                'shift_number',
                'source_branch_code',
                'source_comcode',
                'pax_total',
                'revenue_total',
            ]);
        });
    }
};
