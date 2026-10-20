<?php

namespace App\Console\Commands\Monitoring;

use App\Actions\Monitoring\RecordMonitoringDeploymentAction;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Local ingestion for the VPS deploy script: runs inside the application context, so no HTTP
 * secret is needed. Example:
 * php artisan monitoring:record-deployment --run-id=123 --status=succeeded --stage=completed --commit=abc1234
 */
class RecordDeploymentCommand extends Command
{
    protected $signature = 'monitoring:record-deployment
        {--environment= : Defaults to the monitoring environment}
        {--provider=vps_script}
        {--run-id= : Unique deployment run identifier}
        {--status=started : started|succeeded|failed|cancelled}
        {--stage=}
        {--branch=}
        {--commit=}
        {--message=}
        {--actor=}
        {--migration-status=}
        {--health-status=}
        {--failure-summary=}
        {--started-at=}
        {--finished-at=}
        {--working-tree-dirty : Report that the server working tree had local changes}';

    protected $description = 'Record deployment metadata for System Monitoring (read-only, never deploys)';

    public function handle(RecordMonitoringDeploymentAction $record): int
    {
        try {
            $deployment = $record->execute(array_filter([
                'environment' => $this->option('environment') ?: config('monitoring.environment'),
                'provider' => $this->option('provider'),
                'run_id' => $this->option('run-id'),
                'status' => $this->option('status'),
                'stage' => $this->option('stage'),
                'branch' => $this->option('branch'),
                'commit_sha' => $this->option('commit'),
                'commit_message' => $this->option('message'),
                'actor' => $this->option('actor'),
                'migration_status' => $this->option('migration-status'),
                'health_status' => $this->option('health-status'),
                'failure_summary' => $this->option('failure-summary'),
                'started_at' => $this->option('started-at'),
                'finished_at' => $this->option('finished-at'),
                'metadata' => $this->option('working-tree-dirty') ? ['working_tree_dirty' => true] : null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->components->error($field.': '.implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $this->components->info("Deployment {$deployment->uuid} recorded as {$deployment->status->value}.");

        return self::SUCCESS;
    }
}
