<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceSyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
