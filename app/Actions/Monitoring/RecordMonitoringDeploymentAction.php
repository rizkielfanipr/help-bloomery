<?php

namespace App\Actions\Monitoring;

use App\Enums\MonitoringDeploymentStatus;
use App\Models\MonitoringDeployment;
use App\Services\Monitoring\MonitoringSanitizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Single ingestion path for deployment metadata (docs/products/system-monitoring/prd.md §14), used by the
 * signed HTTP callback and the local `monitoring:record-deployment` command. Re-sending the same
 * provider + run ID updates that deployment (stage progression) instead of creating duplicates.
 */
class RecordMonitoringDeploymentAction
{
    public const PROVIDERS = ['github_actions', 'vps_script', 'manual'];

    /** Only these metadata flags are stored; anything else is dropped. */
    private const METADATA_ALLOWLIST = ['working_tree_dirty', 'queue_restarted', 'pending_migrations', 'release_path_hash', 'php_version'];

    public function __construct(private readonly MonitoringSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function execute(array $payload): MonitoringDeployment
    {
        $data = Validator::make($payload, [
            'environment' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/'],
            'provider' => ['required', Rule::in(self::PROVIDERS)],
            'run_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'status' => ['required', Rule::enum(MonitoringDeploymentStatus::class)],
            'stage' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_ -]+$/i'],
            'branch' => ['nullable', 'string', 'max:120'],
            'commit_sha' => ['nullable', 'string', 'regex:/^[0-9a-f]{7,40}$/i'],
            'commit_message' => ['nullable', 'string', 'max:2000'],
            'actor' => ['nullable', 'string', 'max:120'],
            'migration_status' => ['nullable', Rule::in(['ran', 'pending', 'failed', 'skipped', 'unknown'])],
            'health_status' => ['nullable', Rule::in(['healthy', 'degraded', 'down', 'unknown'])],
            'failure_summary' => ['nullable', 'string', 'max:2000'],
            'started_at' => ['nullable', 'date'],
            'finished_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $deployment = MonitoringDeployment::query()->firstOrNew([
            'environment' => $data['environment'],
            'provider' => $data['provider'],
            'run_id' => $data['run_id'],
        ]);
        $startedAt = isset($data['started_at']) ? Carbon::parse($data['started_at']) : ($deployment->started_at ?? now());
        $finishedAt = isset($data['finished_at']) ? Carbon::parse($data['finished_at']) : ($data['status'] !== MonitoringDeploymentStatus::Started->value ? now() : null);

        $deployment->fill([
            'uuid' => $deployment->uuid ?? (string) Str::uuid(),
            'status' => $data['status'],
            'stage' => $data['stage'] ?? $deployment->stage,
            'branch' => $data['branch'] ?? $deployment->branch,
            'commit_sha' => isset($data['commit_sha']) ? mb_strtolower($data['commit_sha']) : $deployment->commit_sha,
            'commit_message' => isset($data['commit_message']) ? $this->sanitizer->message($data['commit_message'], 500) : $deployment->commit_message,
            'actor' => isset($data['actor']) ? $this->sanitizer->message($data['actor'], 120) : $deployment->actor,
            'migration_status' => $data['migration_status'] ?? $deployment->migration_status,
            'health_status' => $data['health_status'] ?? $deployment->health_status,
            'failure_summary' => isset($data['failure_summary']) ? $this->sanitizer->message($data['failure_summary'], 500) : $deployment->failure_summary,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_seconds' => $finishedAt ? (int) max(0, $startedAt->diffInSeconds($finishedAt)) : null,
            'metadata' => $this->sanitizer->sanitize(Arr::only((array) ($data['metadata'] ?? []), self::METADATA_ALLOWLIST)) ?: $deployment->metadata,
        ])->save();

        Cache::forget('monitoring.deploy_commit.'.$deployment->environment);

        return $deployment;
    }
}
