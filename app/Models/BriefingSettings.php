<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BriefingSettings extends Model
{
    protected $fillable = [
        'auto_reject_after_days',
    ];

    protected function casts(): array
    {
        return [
            'auto_reject_after_days' => 'integer',
        ];
    }

    /**
     * Get or create the single settings instance.
     */
    public static function instance(): static
    {
        return static::firstOrCreate(['id' => 1], [
            'auto_reject_after_days' => 3,
        ]);
    }
}
