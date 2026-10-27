<?php

namespace App\Filament\Helpdesk\Pages\Monitoring;

use App\Models\MonitoringCheckResult;
use App\Models\MonitoringService;
use App\Services\Monitoring\MonitoringAggregator;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use UnitEnum;

/**
 * Service Health (docs/operations/monitoring/prd.md §10): stored snapshots only; checks run in the
 * scheduler, never from this page.
 */
class MonitoringServiceHealthPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|UnitEnum|null $navigationGroup = 'System Monitoring';

    protected static ?string $navigationLabel = 'Service Health';

    protected static ?string $title = 'Service Health';

    protected static ?string $slug = 'system-monitoring/service-health';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.monitoring.service-health';

    protected Width|string|null $maxContentWidth = Width::Full;

    public ?string $selectedServiceKey = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', MonitoringService::class) ?? false;
    }

    /** @return Collection<int, MonitoringService> */
    #[Computed]
    public function services(): Collection
    {
        return MonitoringService::query()->forCurrentEnvironment()->orderBy('group')->orderBy('name')->get();
    }

    /** @return array<int, array{total: int, problems: int}> */
    #[Computed]
    public function last24h(): array
    {
        return MonitoringCheckResult::query()
            ->whereIn('monitoring_service_id', $this->services->pluck('id'))
            ->where('checked_at', '>=', now()->subDay())
            ->groupBy('monitoring_service_id')
            ->selectRaw("monitoring_service_id, count(*) as total, sum(case when status in ('degraded', 'down') then 1 else 0 end) as problems")
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->monitoring_service_id => ['total' => (int) $row->total, 'problems' => (int) $row->problems]])
            ->all();
    }

    /** @return array<string, float|null> */
    #[Computed]
    public function uptime7d(): array
    {
        return app(MonitoringAggregator::class)->uptime(7);
    }

    /** @return Collection<int, MonitoringCheckResult> */
    #[Computed]
    public function recentResults(): Collection
    {
        $service = $this->services->firstWhere('key', $this->selectedServiceKey);

        return $service
            ? $service->checkResults()->latest('checked_at')->limit(20)->get(['id', 'status', 'checked_at', 'response_time_ms', 'http_status', 'safe_message'])
            : collect();
    }

    public function selectService(string $key): void
    {
        $this->selectedServiceKey = $this->services->contains('key', $key) && $this->selectedServiceKey !== $key ? $key : null;
        unset($this->recentResults);
    }
}
