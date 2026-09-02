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
        if (Schema::hasTable('basket_size_employee_records')) {
            return;
        }

        Schema::create('basket_size_employee_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basket_size_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('employee_name');
            $table->string('employee_position')->nullable();
            $table->decimal('basket_size_credit', 18, 2)->nullable();
            $table->timestamps();

            $table->unique(['basket_size_record_id', 'employee_id'], 'basket_employee_unique');
            $table->index(['employee_id', 'basket_size_credit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basket_size_employee_records');
    }
};
