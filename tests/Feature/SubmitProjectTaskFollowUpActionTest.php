<?php

use App\Actions\Rnd\ProjectTask\StartProjectTaskAssignmentAction;
use App\Actions\Rnd\ProjectTask\SubmitProjectTaskFollowUpAction;
use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;

it('starts an assignment and stamps started_at once', function () {
    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'assigned', 'started_at' => null]);

    $started = app(StartProjectTaskAssignmentAction::class)->execute($assignment);

    expect($started->status)->toBe(RndProjectTaskAssignmentStatus::InProgress)
        ->and($started->started_at)->not->toBeNull();
});

it('saves a progress follow-up without changing the assignment status to Submitted', function () {
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'in_progress', 'user_id' => $pic->id]);

    $followUp = app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
        'follow_up_type' => 'progress', 'notes' => 'Masih proses.', 'estimated_completion_date' => null, 'result_attachments' => null,
    ], $pic);

    expect($followUp->follow_up_type->value)->toBe('progress')
        ->and($assignment->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::InProgress);
});

it('auto-starts an Assigned assignment on its first follow-up', function () {
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'assigned', 'user_id' => $pic->id]);

    app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
        'follow_up_type' => 'progress', 'notes' => 'Mulai kerja.', 'estimated_completion_date' => null, 'result_attachments' => null,
    ], $pic);

    expect($assignment->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::InProgress);
});

it('submits a follow-up, moves the assignment to Submitted, and syncs the task status', function () {
    $pic = User::factory()->create();
    $task = RndProjectTask::factory()->create(['status' => 'in_progress']);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'status' => 'in_progress', 'user_id' => $pic->id,
    ]);

    $followUp = app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
        'follow_up_type' => 'submission', 'notes' => 'Selesai.', 'estimated_completion_date' => null, 'result_attachments' => null,
    ], $pic);

    expect($followUp->follow_up_type->value)->toBe('submission')
        ->and($assignment->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::Submitted)
        ->and($assignment->fresh()->submitted_at)->not->toBeNull()
        ->and($task->fresh()->status)->toBe(RndProjectTaskStatus::Submitted);
});

it('rejects submitting twice while the first submission is still pending review', function () {
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'submitted', 'user_id' => $pic->id]);

    expect(fn () => app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
        'follow_up_type' => 'submission', 'notes' => null, 'estimated_completion_date' => null, 'result_attachments' => null,
    ], $pic))->toThrow(RuntimeException::class);
});

it('keeps every follow-up entry instead of overwriting history', function () {
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'in_progress', 'user_id' => $pic->id]);

    app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
        'follow_up_type' => 'progress', 'notes' => 'Progress 1.', 'estimated_completion_date' => null, 'result_attachments' => null,
    ], $pic);
    app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
        'follow_up_type' => 'progress', 'notes' => 'Progress 2.', 'estimated_completion_date' => null, 'result_attachments' => null,
    ], $pic);

    expect($assignment->followUps)->toHaveCount(2);
});
