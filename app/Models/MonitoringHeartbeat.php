<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Latest local heartbeat of the scheduler or queue worker (docs/system-monitoring-prd.md §12).
 */
class MonitoringHeartbeat extends Model
{
    protected $fillable = [
        'environment',
        'key',
        'last_beat_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_beat_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
