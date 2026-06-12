<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casual_position_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('casual_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('casual_shift_id')->constrained()->cascadeOnDelete();
            $table->string('location');
            $table->date('work_date');
            $table->unsignedSmallInteger('total_slots')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casual_position_openings');
    }
};
