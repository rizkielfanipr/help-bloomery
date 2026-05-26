<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverTripSettings extends Model
{
    protected $fillable = [
        'show_fuel_modal',
        'require_fuel_attachment',
        'report_cutoff_day',
    ];

    protected function casts(): array
    {
        return [
            'show_fuel_modal' => 'boolean',
            'require_fuel_attachment' => 'boolean',
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
            'report_cutoff_day' => 20,
        ]);
    }
}
