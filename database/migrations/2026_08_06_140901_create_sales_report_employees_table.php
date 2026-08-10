<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_report_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_position')->nullable();
            $table->timestamps();
        });

        DB::table('sales_reports')
            ->whereNotNull('employee_id')
            ->orderBy('id')
            ->get(['id', 'employee_id', 'employee_code', 'employee_name', 'employee_position'])
            ->each(function (object $report): void {
                DB::table('sales_report_employees')->insert([
                    'sales_report_id' => $report->id,
                    'employee_id' => $report->employee_id,
                    'employee_code' => $report->employee_code,
                    'employee_name' => $report->employee_name,
                    'employee_position' => $report->employee_position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('sales_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['employee_code', 'employee_name', 'employee_position']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_reports', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('submitted_by')->constrained('employees')->nullOnDelete();
            $table->string('employee_code')->nullable()->after('employee_id');
            $table->string('employee_name')->nullable()->after('employee_code');
            $table->string('employee_position')->nullable()->after('employee_name');
        });

        DB::table('sales_report_employees')
            ->orderBy('id')
            ->get()
            ->groupBy('sales_report_id')
            ->each(function ($rows, $salesReportId): void {
                $first = $rows->first();
                DB::table('sales_reports')->where('id', $salesReportId)->update([
                    'employee_id' => $first->employee_id,
                    'employee_code' => $first->employee_code,
                    'employee_name' => $first->employee_name,
                    'employee_position' => $first->employee_position,
                ]);
            });

        Schema::dropIfExists('sales_report_employees');
    }
};
