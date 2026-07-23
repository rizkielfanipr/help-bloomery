<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'sales_shift_1_start')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->time('sales_shift_1_start')->default('09:00:00');
                $table->time('sales_shift_1_end')->default('15:00:00');
                $table->time('sales_shift_2_start')->default('15:00:00');
                $table->time('sales_shift_2_end')->default('21:00:00');
            });
        }

        Schema::table('sales_reports', function (Blueprint $table): void {
            // MySQL may use the old composite unique index to support this FK.
            $table->index('branch_id');
            $table->dropUnique(['branch_id', 'report_date']);
            $table->unsignedTinyInteger('shift_number')->default(1)->after('report_date');
            $table->dateTime('shift_started_at')->nullable()->after('shift_number');
            $table->dateTime('shift_ended_at')->nullable()->after('shift_started_at');
            $table->unique(['branch_id', 'report_date', 'shift_number']);
        });

        Schema::create('sales_report_esb_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->string('sales_num', 50);
            $table->dateTime('sales_date_out');
            $table->decimal('payment_total', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['sales_report_id', 'sales_num']);
            $table->index(['sales_date_out', 'sales_num']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_report_esb_transactions');

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropUnique(['branch_id', 'report_date', 'shift_number']);
            $table->dropColumn(['shift_number', 'shift_started_at', 'shift_ended_at']);
            $table->unique(['branch_id', 'report_date']);
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn([
                'sales_shift_1_start',
                'sales_shift_1_end',
                'sales_shift_2_start',
                'sales_shift_2_end',
            ]);
        });
    }
};
