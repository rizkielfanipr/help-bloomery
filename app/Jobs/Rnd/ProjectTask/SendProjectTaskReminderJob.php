<?php

namespace App\Jobs\Rnd\ProjectTask;

use App\Enums\RndProjectTaskReminderType;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskReminder;
use App\Notifications\ProjectTaskReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one deadline-proximity reminder for one assignment (docs/rnd-project-task-calendar-prd.md
 * §16). Idempotent by design: the `rnd_task_reminder_unique` DB constraint (not an app-level
 * `firstOrCreate` lookup, which would race against Eloquent's `date` cast storing the time as
 * `00:00:00` rather than truncating it — this differs between SQLite, which stores it verbatim,
 * and MySQL, which coerces it — see the reminder pipeline tests) is the actual guard: whichever
 * attempt's `create()` succeeds is the one that sends the notification; a caught unique-constraint
 * violation means another attempt already logged it. Retries are therefore safe, unlike
 * `SyncBomCatalogJob`'s catalog resync which is deliberately non-retryable.
 */
class SendProjectTaskReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(public int $assignmentId, public string $reminderType) {}

    public function handle(): void
    {
        $assignment = RndProjectTaskAssignment::query()->with(['task', 'user'])->find($this->assignmentId);

        if (! $assignment || $assignment->status->stopsReminders()) {
            return;
        }

        $type = RndProjectTaskReminderType::from($this->reminderType);

        try {
            $reminder = RndProjectTaskReminder::query()->create([
                'rnd_project_task_assignment_id' => $assignment->id,
                'reminder_type' => $type->value,
                'reminder_date' => today()->toDateString(),
            ]);
        } catch (QueryException $exception) {
            $alreadyLogged = RndProjectTaskReminder::query()
                ->where('rnd_project_task_assignment_id', $assignment->id)
                ->where('reminder_type', $type->value)
                ->whereDate('reminder_date', today())
                ->exists();

            if (! $alreadyLogged) {
                throw $exception;
            }

            return;
        }

        if ($assignment->user) {
            $notification = new ProjectTaskReminderNotification($assignment, $type);
            $assignment->user->notify($notification);
            $reminder->update(['sent_at' => now(), 'notification_id' => $notification->id]);
        }
    }
}
