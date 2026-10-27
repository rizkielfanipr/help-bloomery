<?php

namespace App\Filament\Helpdesk\Pages\Monitoring;

use App\Models\MonitoringScheduledTaskRun;
use App\Models\MonitoringService;
use App\Services\Monitoring\MonitoringContext;
use App\Services\Monitoring\MonitoringHeartbeats;
use App\Services\Monitoring\MonitoringSanitizer;
use App\Services\Monitoring\MonitoringSettings;
use App\Services\Monitoring\ScheduledTaskRecorder;
use BackedEnum;
use Cron\CronExpression;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Throwable;
use UnitEnum;

/**
 * Queue & Scheduler Monitor (docs/products/system-monitoring/prd.md §12). Strictly read-only: no worker
 * restart, terminal, or command execution, and no failed-job retry because no job has been proven
 * idempotent for UI retry.
 */
class MonitoringQueueSchedulerPage extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'System Monitoring';

    protected static ?string $navigationLabel = 'Queue & Scheduler';

    protected static ?string $title = 'Queue & Scheduler';

    protected static ?string $slug = 'system-monitoring/queue-scheduler';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.monitoring.queue-scheduler';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return ($user?->can('view system monitoring') && $user->can('view monitoring technical details')) ?? false;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function heartbeats(): array
    {
        $heartbeats = app(MonitoringHeartbeats::class);
        $settings = app(MonitoringSettings::class);

        return collect([
            MonitoringHeartbeats::SCHEDULER => (int) $settings->get('heartbeats.scheduler_late_seconds', 180),
            MonitoringHeartbeats::QUEUE => (int) $settings->get('heartbeats.queue_late_seconds', 300),
        ])->map(function (int $late, string $key) use ($heartbeats): array {
            $beat = $heartbeats->last($key);
            $age = $beat ? (int) $beat->last_beat_at->diffInSeconds(now()) : null;

            return [
                'last_beat_at' => $beat?->last_beat_at,
                'age' => $age,
                'late_after' => $late,
                'state' => $age === null ? 'unknown' : ($age <= $late ? 'healthy' : 'late'),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function queueMetadata(): array
    {
        return (array) (MonitoringService::query()->forCurrentEnvironment()->where('key', 'queue')->value('metadata') ?? []);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function scheduledTasks(): array
    {
        $events = collect(app(Schedule::class)->events());
        $keys = $events->map(fn ($event): string => ScheduledTaskRecorder::taskKey($event))->unique()->values();
        $latestRuns = MonitoringScheduledTaskRun::query()
            ->where('environment', MonitoringContext::environment())
            ->whereIn('task_key', $keys)
            ->whereIn('id', MonitoringScheduledTaskRun::query()->selectRaw('max(id)')->where('environment', MonitoringContext::environment())->groupBy('task_key'))
            ->get()
            ->keyBy('task_key');

        return $events->map(function ($event) use ($latestRuns): array {
            $key = ScheduledTaskRecorder::taskKey($event);
            $run = $latestRuns->get($key);
            try {
                $previousDue = Carbon::instance((new CronExpression($event->expression))->getPreviousRunDate(now($event->timezone ?? config('app.timezone')), 0, true));
                $nextDue = $event->nextRunDate();
            } catch (Throwable) {
                [$previousDue, $nextDue] = [null, null];
            }
            $late = $run !== null && $previousDue !== null
                && $previousDue->copy()->addMinutes(2)->isPast()
                && $run->started_at->lt($previousDue->copy()->subMinute());

            return [
                'key' => $key,
                'expression' => $event->expression,
                'overlap' => (bool) $event->withoutOverlapping,
                'next_due' => $nextDue,
                'last_run' => $run,
                'state' => match (true) {
                    $run === null => 'unknown',
                    $run->status === 'running' && $run->started_at->lt(now()->subHour()) => 'stuck',
                    $run->status === 'failed' => 'failed',
                    $late => 'late',
                    default => $run->status,
                },
            ];
        })->values()->all();
    }

    #[Computed]
    public function failedJobs(): ?LengthAwarePaginator
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');
        if (! Schema::hasTable($table)) {
            return null;
        }

        $sanitizer = app(MonitoringSanitizer::class);

        return tap(DB::table($table)->select(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])->orderByDesc('failed_at')->paginate(15, pageName: 'failedPage'), function (LengthAwarePaginator $paginator) use ($sanitizer): void {
            $paginator->setCollection($paginator->getCollection()->map(fn ($job): array => [
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'job' => class_basename((string) (json_decode((string) $job->payload, true)['displayName'] ?? 'Job')),
                'exception' => $sanitizer->message(strtok((string) $job->exception, "\n") ?: null, 300),
                'failed_at' => $job->failed_at,
            ]));
        });
    }
}
