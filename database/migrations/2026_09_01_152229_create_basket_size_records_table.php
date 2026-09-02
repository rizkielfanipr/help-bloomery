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
        Schema::create('basket_size_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_sales_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('report_date');
            $table->unsignedTinyInteger('shift_number');
            $table->string('shift_name', 50);
            $table->time('shift_start_time');
            $table->time('shift_end_time');
            $table->decimal('revenue', 18, 2)->default(0);
            $table->unsignedInteger('total_pax')->default(0);
            $table->decimal('basket_size', 18, 2)->nullable();
            $table->unsignedInteger('staff_count')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['sales_report_id', 'shift_number']);
            $table->index(['branch_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basket_size_records');
    }
};
