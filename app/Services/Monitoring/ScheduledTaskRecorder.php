<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Throwable;

/**
 * Captures scheduled task run history from the scheduler's own events
 * (docs/products/system-monitoring/prd.md §12.2). Command output is never stored.
 */
class ScheduledTaskRecorder
{
    /** @var array<int, MonitoringScheduledTaskRun> */
    private array $running = [];

    public function __construct(private readonly MonitoringSanitizer $sanitizer) {}

    public static function taskKey(Event $task): string
    {
        $summary = (string) ($task->description ?: $task->command);
        if (preg_match("/artisan'?\s+(.+)$/", $summary, $matches) === 1) {
            $summary = $matches[1];
        }

        return mb_substr(trim($summary, " '\""), 0, 191);
    }

    public function starting(ScheduledTaskStarting $event): void
    {
        $this->guard(function () use ($event): void {
            $this->running[spl_object_id($event->task)] = MonitoringScheduledTaskRun::query()->create([
                'environment' => MonitoringContext::environment(),
                'task_key' => self::taskKey($event->task),
                'expression' => $event->task->expression,
                'status' => 'running',
                'started_at' => now(),
            ]);
        });
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $this->guard(function () use ($event): void {
            $run = $this->running[spl_object_id($event->task)] ?? null;
            if (! $run || $run->status !== 'running') {
                return;
            }

            $run->forceFill([
                'status' => (int) $event->task->exitCode === 0 ? 'succeeded' : 'failed',
                'exit_code' => $event->task->exitCode,
                'finished_at' => now(),
                'duration_ms' => (int) round($event->runtime * 1000),
            ])->save();
            unset($this->running[spl_object_id($event->task)]);
        });
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $this->guard(function () use ($event): void {
            $run = $this->running[spl_object_id($event->task)] ?? null;
            $attributes = [
                'status' => 'failed',
                'finished_at' => now(),
                'safe_message' => $this->sanitizer->message($event->exception->getMessage()),
            ];
            if ($run) {
                $run->forceFill($attributes + ['duration_ms' => (int) $run->started_at->diffInMilliseconds(now())])->save();
            } else {
                MonitoringScheduledTaskRun::query()->create($attributes + [
                    'environment' => MonitoringContext::environment(),
                    'task_key' => self::taskKey($event->task),
                    'expression' => $event->task->expression,
                    'started_at' => now(),
                ]);
            }
        });
    }

    public function skipped(ScheduledTaskSkipped $event): void
    {
        $this->guard(fn () => MonitoringScheduledTaskRun::query()->create([
            'environment' => MonitoringContext::environment(),
            'task_key' => self::taskKey($event->task),
            'expression' => $event->task->expression,
            'status' => 'skipped',
            'started_at' => now(),
            'finished_at' => now(),
            'safe_message' => 'Dilewati (overlap atau kondisi jadwal).',
        ]));
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Monitoring must never interfere with scheduled business tasks.
        }
    }
}
