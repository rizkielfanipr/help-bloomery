<?php

namespace App\Models;

use App\Enums\MonitoringServiceStatus;
use Database\Factories\MonitoringCheckResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sanitized health check execution (docs/system-monitoring-prd.md §18.2).
 */
class MonitoringCheckResult extends Model
{
    /** @use HasFactory<MonitoringCheckResultFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monitoring_service_id',
        'status',
        'checked_at',
        'response_time_ms',
        'http_status',
        'safe_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => MonitoringServiceStatus::class,
            'checked_at' => 'datetime',
            'response_time_ms' => 'integer',
            'http_status' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MonitoringService::class, 'monitoring_service_id');
    }
}
