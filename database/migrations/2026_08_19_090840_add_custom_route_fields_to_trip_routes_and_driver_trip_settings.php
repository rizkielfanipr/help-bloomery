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
        Schema::table('trip_routes', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('requires_waypoint_attachment');
            $table->foreignId('created_by_driver_id')->nullable()->after('is_custom')->constrained('users')->nullOnDelete();
        });

        Schema::table('driver_trip_settings', function (Blueprint $table) {
            $table->decimal('custom_route_meal_allowance_amount', 12, 2)->default(0)->after('require_fuel_attachment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_trip_settings', function (Blueprint $table) {
            $table->dropColumn('custom_route_meal_allowance_amount');
        });

        Schema::table('trip_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_driver_id');
            $table->dropColumn('is_custom');
        });
    }
};
