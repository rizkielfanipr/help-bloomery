<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear old incompatible data so unique constraint can be added
        DB::table('sales_report_entries')->truncate();

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->unique(['sales_report_id', 'payment_method_name']);
            $table->foreign('sales_report_id')->references('id')->on('sales_reports')->cascadeOnDelete();
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropColumn(['modal_shift_1', 'modal_shift_2', 'shift_1_submitted_at', 'shift_2_submitted_at']);
            $table->timestamp('submitted_at')->nullable()->after('report_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropColumn('submitted_at');
            $table->decimal('modal_shift_1', 15, 2)->default(0);
            $table->decimal('modal_shift_2', 15, 2)->default(0);
            $table->timestamp('shift_1_submitted_at')->nullable();
            $table->timestamp('shift_2_submitted_at')->nullable();
        });

        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropUnique(['sales_report_id', 'payment_method_name']);
            $table->dropColumn(['payment_method_name', 'sales_system_amount', 'sales_store_amount']);

            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->decimal('shift_1_amount', 15, 2)->default(0);
            $table->decimal('shift_2_amount', 15, 2)->default(0);

            $table->unique(['sales_report_id', 'payment_method_id']);
        });
    }
};
