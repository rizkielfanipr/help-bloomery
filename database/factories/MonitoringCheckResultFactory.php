<?php

namespace Database\Factories;

use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringCheckResult;
use App\Models\MonitoringService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringCheckResult>
 */
class MonitoringCheckResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monitoring_service_id' => MonitoringService::factory(),
            'status' => MonitoringServiceStatus::Healthy,
            'checked_at' => now(),
            'response_time_ms' => fake()->numberBetween(5, 300),
            'safe_message' => 'OK',
        ];
    }
}
