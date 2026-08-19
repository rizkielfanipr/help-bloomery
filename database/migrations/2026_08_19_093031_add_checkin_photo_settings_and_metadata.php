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
        Schema::table('driver_trip_settings', function (Blueprint $table) {
            $table->string('checkin_photo_source', 20)->default('camera')->after('custom_route_meal_allowance_amount');
            $table->boolean('require_checkin_photo')->default(true);
            $table->boolean('require_checkin_location')->default(true);
            $table->boolean('stamp_checkin_timestamp')->default(true);
            $table->boolean('stamp_checkin_coordinates')->default(true);
            $table->boolean('stamp_checkin_driver_name')->default(true);
            $table->boolean('stamp_checkin_waypoint_name')->default(true);
            $table->boolean('stamp_checkin_route_name')->default(true);
            $table->unsignedTinyInteger('checkin_photo_quality')->default(75);
            $table->unsignedSmallInteger('checkin_photo_max_dimension')->default(1600);
            $table->unsignedSmallInteger('checkin_max_location_accuracy')->nullable();
            $table->boolean('allow_checkin_retake')->default(true);
            $table->boolean('require_sequential_checkin')->default(false);
        });

        Schema::table('trip_waypoint_checkins', function (Blueprint $table) {
            $table->decimal('location_accuracy', 8, 2)->nullable()->after('longitude');
            $table->string('photo_source', 20)->nullable()->after('location_accuracy');
            $table->timestamp('device_captured_at')->nullable()->after('photo_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_waypoint_checkins', function (Blueprint $table) {
            $table->dropColumn(['location_accuracy', 'photo_source', 'device_captured_at']);
        });

        Schema::table('driver_trip_settings', function (Blueprint $table) {
            $table->dropColumn([
                'checkin_photo_source', 'require_checkin_photo', 'require_checkin_location',
                'stamp_checkin_timestamp', 'stamp_checkin_coordinates', 'stamp_checkin_driver_name',
                'stamp_checkin_waypoint_name', 'stamp_checkin_route_name', 'checkin_photo_quality',
                'checkin_photo_max_dimension', 'checkin_max_location_accuracy', 'allow_checkin_retake',
                'require_sequential_checkin',
            ]);
        });
    }
};
