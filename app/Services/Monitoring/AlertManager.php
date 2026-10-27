<?php

namespace App\Services\Monitoring;

use App\Actions\Monitoring\CreateMonitoringIncidentAction;
use App\Enums\MonitoringAlertStatus;
use App\Enums\MonitoringErrorStatus;
use App\Enums\MonitoringIncidentSeverity;
use App\Enums\MonitoringServiceStatus;
use App\Models\MonitoringAlert;
use App\Models\MonitoringError;
use App\Models\MonitoringService;
use App\Models\User;
use App\Notifications\MonitoringAlertNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Deduplicated alerting (docs/operations/monitoring/prd.md §17): one active alert per environment +
 * source key, notifications only on state change (open, escalation, recovery) and reminders for
 * unacknowledged alerts. Maintenance windows suppress new alerts but not recording.
 */
class AlertManager
{
    public function __construct(private readonly MonitoringSettings $settings) {}

    public static function serviceDedupKey(MonitoringService $service): string
    {
        return $service->environment.':service:'.$service->key;
    }

    public static function errorDedupKey(MonitoringError $error): string
    {
        return $error->environment.':error:'.$error->fingerprint;
    }

    public function evaluateService(MonitoringService $service, MonitoringServiceStatus $previous, MonitoringServiceStatus $effective): void
    {
        if ($effective === MonitoringServiceStatus::Maintenance) {
            return;
        }

        $active = MonitoringAlert::query()->active()->where('dedup_key', self::serviceDedupKey($service))->first();
        $alertable = $effective === MonitoringServiceStatus::Down || ($effective === MonitoringServiceStatus::Degraded && $service->is_critical);

        if ($alertable && $service->consecutive_failures >= max(1, $service->failure_threshold)) {
            $severity = match (true) {
                $effective === MonitoringServiceStatus::Down && $service->is_critical => MonitoringIncidentSeverity::Sev2,
                $effective === MonitoringServiceStatus::Down => MonitoringIncidentSeverity::Sev3,
                default => MonitoringIncidentSeverity::Sev3,
            };

            if (! $active) {
                $this->open(self::serviceDedupKey($service), 'service', $service->key, "{$service->name} {$effective->getLabel()}", $severity, (string) $service->last_safe_message, $service);
            } elseif ($this->rank($severity) < $this->rank($active->severity)) {
                $active->forceFill(['severity' => $severity, 'safe_message' => $service->last_safe_message])->save();
                $this->notify($active, 'escalated');
            }

            return;
        }

        if ($effective === MonitoringServiceStatus::Healthy && $active) {
            $this->resolve($active);
        }
    }

    public function openForError(MonitoringError $error): void
    {
        $title = 'Error baru: '.class_basename($error->exception_class);
        $this->open(self::errorDedupKey($error), 'error', $error->fingerprint, $title, MonitoringIncidentSeverity::Sev3, (string) $error->safe_message);
    }

    public function resolveForError(MonitoringError $error): void
    {
        if (in_array($error->status, [MonitoringErrorStatus::Resolved, MonitoringErrorStatus::Ignored], true)) {
            $alert = MonitoringAlert::query()->active()->where('dedup_key', self::errorDedupKey($error))->first();
            if ($alert) {
                $this->resolve($alert, notify: false);
            }
        }
    }

    public function acknowledge(MonitoringAlert $alert, User $user): void
    {
        if ($alert->acknowledged_at === null) {
            $alert->forceFill(['acknowledged_at' => now(), 'acknowledged_by' => $user->id])->save();
        }
    }

    /**
     * Re-notify unacknowledged active alerts once per reminder period.
     */
    public function sendReminders(): int
    {
        $minutes = (int) $this->settings->get('alerts.reminder_minutes', 60);
        $sent = 0;

        MonitoringAlert::query()->forCurrentEnvironment()->active()
            ->whereNull('acknowledged_at')
            ->where('suppressed', false)
            ->where('last_notified_at', '<=', now()->subMinutes($minutes))
            ->each(function (MonitoringAlert $alert) use (&$sent): void {
                if (! $this->settings->inMaintenance($alert->source_key)) {
                    $this->notify($alert, 'reminder');
                    $sent++;
                }
            });

        return $sent;
    }

    private function open(string $dedupKey, string $sourceType, string $sourceKey, string $title, MonitoringIncidentSeverity $severity, string $message, ?MonitoringService $service = null): ?MonitoringAlert
    {
        try {
            $alert = MonitoringAlert::query()->create([
                'environment' => MonitoringContext::environment(),
                'dedup_key' => $dedupKey,
                'active_dedup_key' => $dedupKey,
                'source_type' => $sourceType,
                'source_key' => mb_substr($sourceKey, 0, 120),
                'title' => mb_substr($title, 0, 255),
                'severity' => $severity,
                'status' => MonitoringAlertStatus::Active,
                'safe_message' => mb_substr($message, 0, 500),
                'monitoring_service_id' => $service?->id,
                'opened_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $this->notify($alert, 'opened');
        $this->maybeOpenIncident($alert);

        return $alert;
    }

    private function resolve(MonitoringAlert $alert, bool $notify = true): void
    {
        $alert->forceFill([
            'status' => MonitoringAlertStatus::Resolved,
            'active_dedup_key' => null,
            'resolved_at' => now(),
            'duration_seconds' => (int) $alert->opened_at->diffInSeconds(now()),
        ])->save();

        if ($notify) {
            $this->notify($alert, 'recovered');
        }
    }

    private function notify(MonitoringAlert $alert, string $event): void
    {
        $alert->forceFill(['last_notified_at' => now(), 'notification_count' => $alert->notification_count + 1])->save();

        try {
            if ((bool) $this->settings->get('alerts.in_app', true)) {
                Notification::send($this->recipients(), new MonitoringAlertNotification($alert, $event, ['database']));
            }

            $mailRecipients = (array) config('monitoring.alerts.mail_recipients', []);
            if ((bool) $this->settings->get('alerts.mail', false) && $mailRecipients !== []) {
                Notification::route('mail', $mailRecipients)->notify(new MonitoringAlertNotification($alert, $event, ['mail']));
            }
        } catch (Throwable $exception) {
            Log::warning('Monitoring alert notification failed.', ['alert' => $alert->id, 'exception' => $exception::class]);
        }
    }

    private function maybeOpenIncident(MonitoringAlert $alert): void
    {
        if (! (bool) $this->settings->get('alerts.auto_incident', false) || $this->rank($alert->severity) > $this->rank(MonitoringIncidentSeverity::Sev2)) {
            return;
        }

        try {
            app(CreateMonitoringIncidentAction::class)->fromAlert($alert);
        } catch (Throwable $exception) {
            Log::warning('Automatic monitoring incident could not be created.', ['alert' => $alert->id, 'exception' => $exception::class]);
        }
    }

    /** @return Collection<int, User> */
    private function recipients(): Collection
    {
        try {
            return User::permission('acknowledge monitoring alerts')->where('is_active', true)->get()
                ->merge(User::role('SUPERADMIN')->where('is_active', true)->get())
                ->unique('id')
                ->values();
        } catch (Throwable) {
            return collect();
        }
    }

    private function rank(MonitoringIncidentSeverity $severity): int
    {
        return (int) substr($severity->value, 3);
    }
}
