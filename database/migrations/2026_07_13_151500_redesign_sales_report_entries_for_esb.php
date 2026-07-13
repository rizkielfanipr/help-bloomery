<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_report_entries')->truncate();

        // Drop both FKs first — sales_report_id FK uses the composite unique as its backing
        // index, so it must be dropped before the composite unique can be dropped
        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropForeign(['sales_report_id']);
            $table->dropForeign(['payment_method_id']);
        });

        // Drop old unique + old columns, add new columns
        Schema::table('sales_report_entries', function (Blueprint $table): void {
            $table->dropUnique(['sales_report_id', 'payment_method_id']);
            $table->dropColumn(['payment_method_id', 'shift_1_amount', 'shift_2_amount']);
            $table->string('payment_method_name', 100)->after('sales_report_id');
            $table->decimal('sales_system_amount', 15, 2)->default(0)->after('payment_method_name');
            $table->decimal('sales_store_amount', 15, 2)->default(0)->after('sales_system_amount');
        });

        // Add new unique constraint and FK
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
