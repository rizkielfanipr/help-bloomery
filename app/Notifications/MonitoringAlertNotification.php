<?php

namespace App\Notifications;

use App\Filament\Helpdesk\Pages\Monitoring\MonitoringOverviewPage;
use App\Models\MonitoringAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Alert state change notification (docs/products/system-monitoring/prd.md §17). The message only carries
 * the alert title, severity, and sanitized summary — never payloads, stack traces, or secrets.
 * The link still passes the application's own authorization.
 */
class MonitoringAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  'opened'|'escalated'|'recovered'|'reminder'  $event
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly MonitoringAlert $alert,
        public readonly string $event,
        public readonly array $channels = ['database'],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monitoring_alert',
            'event' => $this->event,
            'monitoring_alert_id' => $this->alert->id,
            'severity' => $this->alert->severity->getLabel(),
            'title' => $this->title(),
            'message' => $this->alert->safe_message,
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Monitoring] '.$this->title())
            ->line($this->alert->safe_message ?? '')
            ->action('Buka System Monitoring', $this->url() ?? url('/'));
    }

    private function title(): string
    {
        $prefix = match ($this->event) {
            'opened' => 'Gangguan',
            'escalated' => 'Eskalasi',
            'recovered' => 'Pulih',
            default => 'Pengingat',
        };
        $suffix = $this->event === 'recovered' && $this->alert->duration_seconds !== null
            ? ' setelah '.max(1, (int) ceil($this->alert->duration_seconds / 60)).' menit'
            : '';

        return "{$prefix} [{$this->alert->severity->getLabel()}]: {$this->alert->title}{$suffix}";
    }

    private function url(): ?string
    {
        try {
            return MonitoringOverviewPage::getUrl(panel: 'helpdesk');
        } catch (Throwable) {
            return null;
        }
    }
}
