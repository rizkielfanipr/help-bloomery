<?php

namespace Database\Factories;

use App\Enums\MonitoringAlertStatus;
use App\Enums\MonitoringIncidentSeverity;
use App\Models\MonitoringAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringAlert>
 */
class MonitoringAlertFactory extends Factory
{
    public function definition(): array
    {
        $key = config('monitoring.environment').':service:'.fake()->unique()->lexify('??????');

        return [
            'environment' => config('monitoring.environment'),
            'dedup_key' => $key,
            'active_dedup_key' => $key,
            'source_type' => 'service',
            'source_key' => 'database',
            'title' => 'Database Down',
            'severity' => MonitoringIncidentSeverity::Sev2,
            'status' => MonitoringAlertStatus::Active,
            'safe_message' => 'Pemeriksaan gagal.',
            'opened_at' => now()->subMinutes(10),
        ];
    }
}
