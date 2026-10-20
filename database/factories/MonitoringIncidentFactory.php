<?php

namespace Database\Factories;

use App\Enums\MonitoringIncidentSeverity;
use App\Enums\MonitoringIncidentStatus;
use App\Models\MonitoringIncident;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MonitoringIncident>
 */
class MonitoringIncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'number' => 'INC-'.fake()->unique()->numerify('######'),
            'environment' => config('monitoring.environment'),
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'severity' => MonitoringIncidentSeverity::Sev3,
            'status' => MonitoringIncidentStatus::Open,
            'affected_services' => ['database'],
            'detected_at' => now()->subMinutes(30),
        ];
    }
}
