<?php

namespace Database\Factories;

use App\Enums\MonitoringDeploymentStatus;
use App\Models\MonitoringDeployment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MonitoringDeployment>
 */
class MonitoringDeploymentFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now()->subMinutes(30);

        return [
            'uuid' => (string) Str::uuid(),
            'environment' => config('monitoring.environment'),
            'provider' => 'github_actions',
            'run_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'branch' => 'main',
            'commit_sha' => sha1(Str::random(10)),
            'commit_message' => 'feat: example change',
            'actor' => 'deploy-bot',
            'status' => MonitoringDeploymentStatus::Succeeded,
            'stage' => 'completed',
            'migration_status' => 'ran',
            'health_status' => 'healthy',
            'started_at' => $startedAt,
            'finished_at' => $startedAt->copy()->addMinutes(4),
            'duration_seconds' => 240,
        ];
    }
}
