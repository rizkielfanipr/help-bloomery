<?php

namespace App\Services\Monitoring;

use App\Enums\MonitoringDeploymentStatus;
use App\Models\MonitoringDeployment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Throwable;

/**
 * Process-wide monitoring context: current environment, request correlation ID, and the deployed
 * commit when it can be proven (config value or the latest succeeded deployment record).
 */
final class MonitoringContext
{
    public const CORRELATION_KEY = 'correlation_id';

    public static function environment(): string
    {
        return (string) config('monitoring.environment', config('app.env'));
    }

    /**
     * Returns the correlation ID shared through Laravel Context (also propagated to queued jobs
     * and log entries), creating one when the current process has none yet.
     */
    public static function correlationId(): string
    {
        $current = Context::get(self::CORRELATION_KEY);
        if (is_string($current) && Str::isUuid($current)) {
            return $current;
        }

        $correlationId = (string) Str::uuid();
        Context::add(self::CORRELATION_KEY, $correlationId);

        return $correlationId;
    }

    public static function deployCommit(): ?string
    {
        $configured = trim((string) config('monitoring.deploy_commit', ''));
        if ($configured !== '') {
            return substr($configured, 0, 64);
        }

        try {
            return Cache::remember('monitoring.deploy_commit.'.self::environment(), now()->addMinutes(5), fn (): ?string => MonitoringDeployment::query()
                ->forCurrentEnvironment()
                ->where('status', MonitoringDeploymentStatus::Succeeded->value)
                ->latest('finished_at')
                ->value('commit_sha'));
        } catch (Throwable) {
            return null;
        }
    }
}
