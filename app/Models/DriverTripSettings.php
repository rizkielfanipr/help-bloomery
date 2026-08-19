<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverTripSettings extends Model
{
    protected $fillable = [
        'show_fuel_modal',
        'require_fuel_attachment',
        'custom_route_meal_allowance_amount',
        'checkin_photo_source',
        'require_checkin_photo',
        'require_checkin_location',
        'require_waypoint_radius',
        'default_waypoint_radius',
        'stamp_checkin_timestamp',
        'stamp_checkin_coordinates',
        'stamp_checkin_driver_name',
        'stamp_checkin_waypoint_name',
        'stamp_checkin_route_name',
        'checkin_photo_quality',
        'checkin_photo_max_dimension',
        'checkin_max_location_accuracy',
        'allow_checkin_retake',
        'require_sequential_checkin',
        'report_cutoff_day',
    ];

    protected function casts(): array
    {
        return [
            'show_fuel_modal' => 'boolean',
            'require_fuel_attachment' => 'boolean',
            'custom_route_meal_allowance_amount' => 'decimal:2',
            'require_checkin_photo' => 'boolean',
            'require_checkin_location' => 'boolean',
            'require_waypoint_radius' => 'boolean',
            'default_waypoint_radius' => 'integer',
            'stamp_checkin_timestamp' => 'boolean',
            'stamp_checkin_coordinates' => 'boolean',
            'stamp_checkin_driver_name' => 'boolean',
            'stamp_checkin_waypoint_name' => 'boolean',
            'stamp_checkin_route_name' => 'boolean',
            'checkin_photo_quality' => 'integer',
            'checkin_photo_max_dimension' => 'integer',
            'checkin_max_location_accuracy' => 'integer',
            'allow_checkin_retake' => 'boolean',
            'require_sequential_checkin' => 'boolean',
            'report_cutoff_day' => 'integer',
        ];
    }

    /**
     * Get or create the single settings instance.
     */
    public static function instance(): static
    {
        return static::firstOrCreate(['id' => 1], [
            'show_fuel_modal' => true,
            'require_fuel_attachment' => true,
            'custom_route_meal_allowance_amount' => 0,
            'checkin_photo_source' => 'camera',
            'require_checkin_photo' => true,
            'require_checkin_location' => true,
            'require_waypoint_radius' => false,
            'default_waypoint_radius' => 100,
            'stamp_checkin_timestamp' => true,
            'stamp_checkin_coordinates' => true,
            'stamp_checkin_driver_name' => true,
            'stamp_checkin_waypoint_name' => true,
            'stamp_checkin_route_name' => true,
            'checkin_photo_quality' => 75,
            'checkin_photo_max_dimension' => 1600,
            'checkin_max_location_accuracy' => null,
            'allow_checkin_retake' => true,
            'require_sequential_checkin' => false,
            'report_cutoff_day' => 20,
        ]);
    }
}
