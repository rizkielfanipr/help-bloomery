<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->foreignId('employee_id')
                ->nullable()
                ->after('submitted_by')
                ->constrained('employees')
                ->nullOnDelete();
            $table->string('employee_code')->nullable()->after('employee_id');
            $table->string('employee_name')->nullable()->after('employee_code');
            $table->string('employee_position')->nullable()->after('employee_name');
        });
    }

    public function down(): void
    {
        Schema::table('sales_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['employee_code', 'employee_name', 'employee_position']);
        });

        Schema::dropIfExists('employees');
    }
};
