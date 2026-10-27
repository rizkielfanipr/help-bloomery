<?php

namespace App\Models;

use App\Enums\MonitoringDeploymentStatus;
use App\Services\Monitoring\MonitoringContext;
use Database\Factories\MonitoringDeploymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only deployment metadata (docs/operations/monitoring/prd.md §14). The dashboard never deploys
 * or rolls back.
 */
class MonitoringDeployment extends Model
{
    /** @use HasFactory<MonitoringDeploymentFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'environment',
        'provider',
        'run_id',
        'branch',
        'commit_sha',
        'commit_message',
        'actor',
        'status',
        'stage',
        'migration_status',
        'health_status',
        'failure_summary',
        'started_at',
        'finished_at',
        'duration_seconds',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => MonitoringDeploymentStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_seconds' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @param Builder<MonitoringDeployment> $query */
    public function scopeForCurrentEnvironment(Builder $query): void
    {
        $query->where('environment', MonitoringContext::environment());
    }

    public function shortSha(): ?string
    {
        return $this->commit_sha ? substr($this->commit_sha, 0, 7) : null;
    }

    public function isLongRunning(): bool
    {
        return $this->status === MonitoringDeploymentStatus::Started
            && $this->started_at?->copy()->addMinutes((int) config('monitoring.deployments.long_running_minutes', 20))->isPast();
    }
}
