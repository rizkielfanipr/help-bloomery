<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily rollup used by Overview trends (docs/operations/monitoring/prd.md §19). `date` is kept as a
 * plain Y-m-d string so re-aggregating a day matches the existing row on every driver.
 */
class MonitoringDailyAggregate extends Model
{
    protected $fillable = [
        'date',
        'environment',
        'metric',
        'dimension',
        'total',
        'problems',
        'avg_response_ms',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'problems' => 'integer',
            'avg_response_ms' => 'integer',
        ];
    }
}
