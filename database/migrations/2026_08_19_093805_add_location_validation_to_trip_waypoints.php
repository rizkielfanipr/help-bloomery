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
        Schema::table('trip_route_waypoints', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('description');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('radius_meters')->default(100)->after('longitude');
        });

        Schema::table('driver_trip_settings', function (Blueprint $table) {
            $table->boolean('require_waypoint_radius')->default(false)->after('require_checkin_location');
            $table->unsignedSmallInteger('default_waypoint_radius')->default(100)->after('require_waypoint_radius');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_trip_settings', function (Blueprint $table) {
            $table->dropColumn(['require_waypoint_radius', 'default_waypoint_radius']);
        });

        Schema::table('trip_route_waypoints', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_meters']);
        });
    }
};
