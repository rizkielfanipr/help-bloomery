<?php

namespace Database\Factories;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringService>
 */
class MonitoringServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'environment' => config('monitoring.environment'),
            'key' => 'service_'.fake()->unique()->lexify('??????'),
            'name' => fake()->words(2, true),
            'group' => 'core',
            'is_enabled' => true,
            'is_critical' => false,
            'check_interval_seconds' => 60,
            'timeout_seconds' => 5,
            'failure_threshold' => 2,
            'recovery_threshold' => 1,
            'last_status' => MonitoringServiceStatus::Unknown,
        ];
    }

    public function healthy(): static
    {
        return $this->state(fn (): array => [
            'last_status' => MonitoringServiceStatus::Healthy,
            'last_observed_status' => MonitoringServiceStatus::Healthy,
            'last_checked_at' => now(),
            'status_changed_at' => now()->subHour(),
            'last_response_time_ms' => 12,
            'last_safe_message' => 'OK',
        ]);
    }

    public function down(): static
    {
        return $this->state(fn (): array => [
            'last_status' => MonitoringServiceStatus::Down,
            'last_observed_status' => MonitoringServiceStatus::Down,
            'last_checked_at' => now(),
            'status_changed_at' => now()->subMinutes(5),
            'consecutive_failures' => 3,
            'last_safe_message' => 'Pemeriksaan gagal.',
        ]);
    }
}
