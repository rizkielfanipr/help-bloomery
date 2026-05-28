<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianSettings extends Model
{
    protected $fillable = [
        'max_jobs_per_day',
    ];

    public static function instance(): self
    {
        return self::firstOrCreate([], ['max_jobs_per_day' => 5]);
    }
}
