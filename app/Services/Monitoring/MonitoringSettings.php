<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Non-secret monitoring settings: validated overrides stored in `monitoring_settings`, falling
 * back to `config/monitoring.php`. Secrets are never stored here.
 */
class MonitoringSettings
{
    public const EDITABLE_KEYS = [
        'alerts.in_app',
        'alerts.mail',
        'alerts.reminder_minutes',
        'alerts.auto_incident',
        'heartbeats.scheduler_late_seconds',
        'heartbeats.queue_late_seconds',
        'maintenance.window',
        'retention.check_results_days',
        'retention.transactions_days',
        'retention.payload_days',
        'retention.errors_days',
        'retention.deployments_days',
    ];

    private const CACHE_KEY = 'monitoring.settings.v1';

    public function get(string $key, mixed $default = null): mixed
    {
        $overrides = $this->overrides();

        return array_key_exists($key, $overrides) ? $overrides[$key] : config('monitoring.'.$key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values, ?User $actor): void
    {
        foreach ($values as $key => $value) {
            if (! in_array($key, self::EDITABLE_KEYS, true)) {
                continue;
            }

            MonitoringSetting::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value], 'updated_by' => $actor?->id]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason: ?string, service_keys: list<string>}|null
     */
    public function maintenanceWindow(): ?array
    {
        $window = $this->get('maintenance.window');
        if (! is_array($window) || blank($window['starts_at'] ?? null) || blank($window['ends_at'] ?? null)) {
            return null;
        }

        return [
            'starts_at' => CarbonImmutable::parse($window['starts_at']),
            'ends_at' => CarbonImmutable::parse($window['ends_at']),
            'reason' => $window['reason'] ?? null,
            'service_keys' => array_values(array_filter((array) ($window['service_keys'] ?? []))),
        ];
    }

    public function inMaintenance(?string $serviceKey = null): bool
    {
        $window = $this->maintenanceWindow();
        if (! $window || now()->lt($window['starts_at']) || now()->gt($window['ends_at'])) {
            return false;
        }

        return $window['service_keys'] === [] || $serviceKey === null || in_array($serviceKey, $window['service_keys'], true);
    }

    /** @return array<string, mixed> */
    private function overrides(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), fn (): array => MonitoringSetting::query()
                ->pluck('value', 'key')
                ->map(fn (mixed $value): mixed => is_array($value) && array_key_exists('v', $value) ? $value['v'] : null)
                ->all());
        } catch (Throwable) {
            return [];
        }
    }
}
