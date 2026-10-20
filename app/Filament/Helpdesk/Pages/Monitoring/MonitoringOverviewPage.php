<?php

namespace App\Filament\Helpdesk\Pages\Monitoring;

use App\Models\MonitoringAlert;
use App\Services\Monitoring\AlertManager;
use App\Services\Monitoring\MonitoringOverview;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * System Monitoring Overview (docs/system-monitoring-prd.md §9, §21.1). Reads the cached local
 * snapshot only; it never calls ESB or other external systems while rendering.
 */
class MonitoringOverviewPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|UnitEnum|null $navigationGroup = 'System Monitoring';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'System Monitoring';

    protected static ?string $slug = 'system-monitoring';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.monitoring.overview';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view system monitoring') ?? false;
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return app(MonitoringOverview::class)->snapshot();
    }

    public function canSeeTechnical(): bool
    {
        return Auth::user()?->can('view monitoring technical details') ?? false;
    }

    public function canAcknowledge(): bool
    {
        return Auth::user()?->can('acknowledge monitoring alerts') ?? false;
    }

    /** @return Collection<int, array{id: string, title: string, message: ?string, created_at: string}> */
    public function myNotifications(): Collection
    {
        return Auth::user()->unreadNotifications()
            ->where('data->type', 'monitoring_alert')
            ->latest()
            ->limit(5)
            ->get(['id', 'data', 'created_at'])
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => (string) ($notification->data['title'] ?? 'Monitoring'),
                'message' => $notification->data['message'] ?? null,
                'created_at' => $notification->created_at->toIso8601String(),
            ]);
    }

    public function acknowledgeAlert(int $alertId): void
    {
        $alert = MonitoringAlert::query()->forCurrentEnvironment()->active()->findOrFail($alertId);
        abort_unless(Gate::allows('acknowledge', $alert), 403);

        app(AlertManager::class)->acknowledge($alert, Auth::user());
        app(MonitoringOverview::class)->forget();
        Notification::make()->title('Alert diakui')->success()->send();
    }

    public function markNotificationsRead(): void
    {
        Auth::user()->unreadNotifications()->where('data->type', 'monitoring_alert')->update(['read_at' => now()]);
    }

    public function refreshSnapshot(): void
    {
        app(MonitoringOverview::class)->forget();
    }
}
