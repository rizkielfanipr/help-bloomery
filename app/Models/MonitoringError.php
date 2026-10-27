<?php

namespace App\Models;

use App\Enums\MonitoringErrorStatus;
use App\Services\Monitoring\MonitoringContext;
use Database\Factories\MonitoringErrorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One deterministic error fingerprint with occurrence counters and triage state
 * (docs/products/system-monitoring/prd.md §13).
 */
class MonitoringError extends Model
{
    /** @use HasFactory<MonitoringErrorFactory> */
    use HasFactory;

    public const INDEX_COLUMNS = [
        'id', 'uuid', 'environment', 'source', 'fingerprint', 'exception_class', 'safe_message', 'module',
        'route_name', 'http_status', 'severity', 'status', 'is_regression', 'occurrence_count',
        'first_seen_at', 'last_seen_at', 'last_deploy_commit',
    ];

    protected $fillable = [
        'uuid',
        'environment',
        'source',
        'fingerprint',
        'exception_class',
        'safe_message',
        'module',
        'route_name',
        'action',
        'http_status',
        'severity',
        'status',
        'is_regression',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'last_correlation_id',
        'last_actor_id',
        'first_deploy_commit',
        'last_deploy_commit',
        'triaged_by',
        'triaged_at',
        'triage_note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => MonitoringErrorStatus::class,
            'is_regression' => 'boolean',
            'occurrence_count' => 'integer',
            'http_status' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'triaged_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function triagedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    /** @param Builder<MonitoringError> $query */
    public function scopeForCurrentEnvironment(Builder $query): void
    {
        $query->where('environment', MonitoringContext::environment());
    }
}
