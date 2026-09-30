<?php

use App\Console\Commands\SendRndProjectTaskRemindersCommand;
use App\Enums\RndProjectTaskReminderType;
use App\Jobs\Rnd\ProjectTask\SendProjectTaskReminderJob;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskReminder;
use App\Models\User;
use App\Notifications\ProjectTaskReminderNotification;
use App\Services\Rnd\ProjectTask\ProjectTaskReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

afterEach(function () {
    Carbon::setTestNow();
});

it('finds assignments due in 3 days, 1 day, today, and overdue', function () {
    Carbon::setTestNow('2026-10-10');

    $due3 = RndProjectTaskAssignment::factory()->create(['status' => 'assigned']);
    $due3->task->update(['due_date' => '2026-10-13']);
    $due1 = RndProjectTaskAssignment::factory()->create(['status' => 'assigned']);
    $due1->task->update(['due_date' => '2026-10-11']);
    $dueToday = RndProjectTaskAssignment::factory()->create(['status' => 'in_progress']);
    $dueToday->task->update(['due_date' => '2026-10-10']);
    $overdue = RndProjectTaskAssignment::factory()->create(['status' => 'revision_required']);
    $overdue->task->update(['due_date' => '2026-10-05']);
    $notDue = RndProjectTaskAssignment::factory()->create(['status' => 'assigned']);
    $notDue->task->update(['due_date' => '2026-10-20']);

    $due = app(ProjectTaskReminderService::class)->dueReminders();
    $byAssignment = $due->keyBy('assignment_id');

    expect($byAssignment->get($due3->id)['reminder_type'])->toBe(RndProjectTaskReminderType::DueIn3Days->value)
        ->and($byAssignment->get($due1->id)['reminder_type'])->toBe(RndProjectTaskReminderType::DueIn1Day->value)
        ->and($byAssignment->get($dueToday->id)['reminder_type'])->toBe(RndProjectTaskReminderType::DueToday->value)
        ->and($byAssignment->get($overdue->id)['reminder_type'])->toBe(RndProjectTaskReminderType::Overdue->value)
        ->and($byAssignment->has($notDue->id))->toBeFalse();
});

it('excludes assignments whose status already stops reminders', function () {
    Carbon::setTestNow('2026-10-10');

    $submitted = RndProjectTaskAssignment::factory()->create(['status' => 'submitted']);
    $submitted->task->update(['due_date' => '2026-10-05']);
    $approved = RndProjectTaskAssignment::factory()->create(['status' => 'approved']);
    $approved->task->update(['due_date' => '2026-10-05']);
    $cancelled = RndProjectTaskAssignment::factory()->create(['status' => 'cancelled']);
    $cancelled->task->update(['due_date' => '2026-10-05']);

    $due = app(ProjectTaskReminderService::class)->dueReminders();

    expect($due->pluck('assignment_id'))
        ->not->toContain($submitted->id)
        ->not->toContain($approved->id)
        ->not->toContain($cancelled->id);
});

it('excludes a reminder already logged for the same assignment, type, and date', function () {
    Carbon::setTestNow('2026-10-10');

    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'assigned']);
    $assignment->task->update(['due_date' => '2026-10-10']);
    RndProjectTaskReminder::factory()->create([
        'rnd_project_task_assignment_id' => $assignment->id,
        'reminder_type' => 'due_today',
        'reminder_date' => '2026-10-10',
    ]);

    $due = app(ProjectTaskReminderService::class)->dueReminders();

    expect($due->pluck('assignment_id'))->not->toContain($assignment->id);
});

it('sends the reminder notification and logs it with a notification_id', function () {
    Notification::fake();
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['user_id' => $pic->id, 'status' => 'assigned']);

    (new SendProjectTaskReminderJob($assignment->id, 'due_today'))->handle();

    Notification::assertSentTo($pic, ProjectTaskReminderNotification::class);
    $reminder = RndProjectTaskReminder::query()->where('rnd_project_task_assignment_id', $assignment->id)->sole();
    expect($reminder->sent_at)->not->toBeNull()
        ->and($reminder->notification_id)->not->toBeNull();
});

it('does not send a duplicate reminder notification on a second run for the same day', function () {
    Notification::fake();
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['user_id' => $pic->id, 'status' => 'assigned']);

    (new SendProjectTaskReminderJob($assignment->id, 'due_today'))->handle();
    (new SendProjectTaskReminderJob($assignment->id, 'due_today'))->handle();

    Notification::assertSentToTimes($pic, ProjectTaskReminderNotification::class, 1);
    expect(RndProjectTaskReminder::query()->where('rnd_project_task_assignment_id', $assignment->id)->count())->toBe(1);
});

it('skips a reminder job when the assignment no longer needs one', function () {
    Notification::fake();
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['user_id' => $pic->id, 'status' => 'approved']);

    (new SendProjectTaskReminderJob($assignment->id, 'due_today'))->handle();

    Notification::assertNothingSent();
    expect(RndProjectTaskReminder::query()->count())->toBe(0);
});

it('dispatches one reminder job per due assignment from the scheduled command', function () {
    Carbon::setTestNow('2026-10-10');
    Bus::fake();

    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'assigned']);
    $assignment->task->update(['due_date' => '2026-10-10']);

    $this->artisan(SendRndProjectTaskRemindersCommand::class)->assertSuccessful();

    Bus::assertDispatched(SendProjectTaskReminderJob::class, fn ($job) => $job->assignmentId === $assignment->id && $job->reminderType === 'due_today');
});
