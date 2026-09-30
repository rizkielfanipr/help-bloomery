<?php

use App\Actions\Rnd\ProjectTask\ReviewProjectTaskFollowUpAction;
use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('approves a submitted assignment and completes the task once every active assignment is approved', function () {
    $reviewer = User::factory()->create();
    $task = RndProjectTask::factory()->create(['status' => 'submitted']);
    $assignment = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'submitted']);

    $reviewed = app(ReviewProjectTaskFollowUpAction::class)->execute($assignment, 'approve', null, $reviewer);

    expect($reviewed->status)->toBe(RndProjectTaskAssignmentStatus::Approved)
        ->and($reviewed->reviewed_by)->toBe($reviewer->id)
        ->and($reviewed->reviewed_at)->not->toBeNull()
        ->and($task->fresh()->status)->toBe(RndProjectTaskStatus::Completed);
});

it('requests revision with a required note and reactivates the PIC obligation', function () {
    $reviewer = User::factory()->create();
    $task = RndProjectTask::factory()->create(['status' => 'submitted']);
    $assignment = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'submitted']);

    $reviewed = app(ReviewProjectTaskFollowUpAction::class)->execute($assignment, 'revision', 'Rasa masih terlalu manis, coba kurangi gula.', $reviewer);

    expect($reviewed->status)->toBe(RndProjectTaskAssignmentStatus::RevisionRequired)
        ->and($reviewed->review_note)->toBe('Rasa masih terlalu manis, coba kurangi gula.')
        ->and($task->fresh()->status)->toBe(RndProjectTaskStatus::RevisionRequired);
});

it('rejects a revision request without a note', function () {
    $reviewer = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'submitted']);

    expect(fn () => app(ReviewProjectTaskFollowUpAction::class)->execute($assignment, 'revision', null, $reviewer))
        ->toThrow(ValidationException::class);
});

it('refuses to review an assignment that is not Submitted', function () {
    $reviewer = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['status' => 'in_progress']);

    expect(fn () => app(ReviewProjectTaskFollowUpAction::class)->execute($assignment, 'approve', null, $reviewer))
        ->toThrow(RuntimeException::class);
});

it('keeps the task in Revision Required with mixed approved and revision-required assignments', function () {
    $reviewer = User::factory()->create();
    $task = RndProjectTask::factory()->create(['status' => 'submitted']);
    $approved = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'submitted']);
    $needsRevision = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'submitted']);

    app(ReviewProjectTaskFollowUpAction::class)->execute($approved, 'approve', null, $reviewer);
    app(ReviewProjectTaskFollowUpAction::class)->execute($needsRevision, 'revision', 'Perlu perbaikan.', $reviewer);

    expect($task->fresh()->status)->toBe(RndProjectTaskStatus::RevisionRequired);
});
