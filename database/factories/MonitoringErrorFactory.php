<?php

namespace Database\Factories;

use App\Enums\MonitoringErrorStatus;
use App\Models\MonitoringError;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MonitoringError>
 */
class MonitoringErrorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'environment' => config('monitoring.environment'),
            'source' => 'backend',
            'fingerprint' => hash('sha256', Str::random(20)),
            'exception_class' => 'RuntimeException',
            'safe_message' => 'Terjadi kesalahan.',
            'module' => 'helpdesk',
            'route_name' => 'filament.helpdesk.pages.dashboard',
            'http_status' => 500,
            'severity' => 'error',
            'status' => MonitoringErrorStatus::New,
            'occurrence_count' => 1,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
            'metadata' => ['frames' => ['app/Example.php:10']],
        ];
    }
}
