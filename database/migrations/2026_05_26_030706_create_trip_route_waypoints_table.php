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
        Schema::create('trip_route_waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_route_id')->constrained('trip_routes')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_route_waypoints');
    }
};
