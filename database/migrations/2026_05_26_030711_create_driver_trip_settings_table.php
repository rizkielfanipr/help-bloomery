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
        Schema::create('driver_trip_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('show_fuel_modal')->default(true);
            $table->boolean('require_fuel_attachment')->default(true);
            $table->unsignedTinyInteger('report_cutoff_day')->default(20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_trip_settings');
    }
};
