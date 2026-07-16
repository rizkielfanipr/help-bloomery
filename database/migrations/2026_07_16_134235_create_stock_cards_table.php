<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('report_date');
            $table->string('flag_unit', 20)->default('stockUnit');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_cards');
    }
};
