<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_periods');
    }

    public function down(): void
    {
        Schema::create('attendance_periods', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
