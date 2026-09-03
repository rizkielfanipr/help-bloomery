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
        Schema::create('sales_report_compliments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('shift_number');
            $table->foreignId('compliment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('compliment_type_name', 100);
            $table->json('attachment_paths');
            $table->text('notes');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_report_id', 'shift_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_report_compliments');
    }
};
