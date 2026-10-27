<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One scheduled task execution captured from scheduler events (docs/products/system-monitoring/prd.md §12.2).
 */
class MonitoringScheduledTaskRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'environment',
        'task_key',
        'expression',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'exit_code',
        'safe_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'exit_code' => 'integer',
        ];
    }
}
