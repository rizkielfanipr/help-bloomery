<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->decimal('original_sales_store_amount', 15, 2)->nullable()->after('sales_store_amount');
            $table->text('original_notes')->nullable()->after('notes');
        });

        DB::table('sales_report_entries')->update([
            'original_sales_store_amount' => DB::raw('sales_store_amount'),
            'original_notes' => DB::raw('notes'),
        ]);

        DB::table('sales_reports')
            ->whereIn('status', ['rejected_by_supervisor', 'rejected_by_finance'])
            ->update(['status' => 'pending_supervisor']);
    }

    public function down(): void
    {
        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropColumn(['original_sales_store_amount', 'original_notes']);
        });
    }
};
