<?php

use App\Actions\Rnd\ProjectTask\AssignProjectTaskAction;
use App\Enums\RndProjectTaskAssignmentStatus;
use App\Models\Branch;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('adds a new PIC assignment and attaches the branch if not already targeted', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);

    $assignment = app(AssignProjectTaskAction::class)->execute($task, $branch->id, $pic->id);

    expect($assignment->status)->toBe(RndProjectTaskAssignmentStatus::Assigned)
        ->and($task->fresh()->branches->pluck('id'))->toContain($branch->id);
});

it('keeps an existing PIC untouched when a second PIC is added to the same branch', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);
    $branch = Branch::factory()->create();
    $task->branches()->attach($branch->id);
    $firstPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $firstPic->syncBranchAccess([$branch->id], $branch->id);
    $existing = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $firstPic->id,
        'status' => 'in_progress',
    ]);

    $secondPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $secondPic->syncBranchAccess([$branch->id], $branch->id);
    app(AssignProjectTaskAction::class)->execute($task, $branch->id, $secondPic->id);

    expect($existing->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::InProgress)
        ->and($task->fresh()->assignments)->toHaveCount(2);
});

it('reassigns a task by cancelling the old assignment and creating a new one for history', function () {
    $branch = Branch::factory()->create();
    $oldPic = User::factory()->create(['is_active' => false, 'access_all_branches' => false]);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'branch_id' => $branch->id, 'user_id' => $oldPic->id, 'status' => 'in_progress',
    ]);
    $newPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $newPic->syncBranchAccess([$branch->id], $branch->id);

    $newAssignment = app(AssignProjectTaskAction::class)->reassign($assignment, $newPic->id);

    expect($assignment->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::Cancelled)
        ->and($newAssignment->status)->toBe(RndProjectTaskAssignmentStatus::Assigned)
        ->and($newAssignment->user_id)->toBe($newPic->id);
});

it('rejects assigning an ineligible user as PIC', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);
    $branch = Branch::factory()->create();
    $inactiveUser = User::factory()->create(['is_active' => false, 'access_all_branches' => false]);
    $inactiveUser->syncBranchAccess([$branch->id], $branch->id);

    expect(fn () => app(AssignProjectTaskAction::class)->execute($task, $branch->id, $inactiveUser->id))
        ->toThrow(ValidationException::class);
});

it('refuses to assign on a task that already reached a terminal status', function () {
    $task = RndProjectTask::factory()->create(['status' => 'completed']);
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);

    expect(fn () => app(AssignProjectTaskAction::class)->execute($task, $branch->id, $pic->id))
        ->toThrow(RuntimeException::class);
});

it('rejects a duplicate-submission assign of the same PIC to the same branch instead of a raw DB error', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);

    app(AssignProjectTaskAction::class)->execute($task, $branch->id, $pic->id);

    expect(fn () => app(AssignProjectTaskAction::class)->execute($task, $branch->id, $pic->id))
        ->toThrow(ValidationException::class);
    expect($task->fresh()->assignments)->toHaveCount(1);
});

it('reactivates a previously-cancelled assignment instead of inserting a duplicate row', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);
    $cancelled = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $pic->id, 'status' => 'cancelled',
    ]);

    $reactivated = app(AssignProjectTaskAction::class)->execute($task, $branch->id, $pic->id);

    expect($reactivated->id)->toBe($cancelled->id)
        ->and($reactivated->status->value)->toBe('assigned')
        ->and($task->fresh()->assignments)->toHaveCount(1);
});

it('reactivates a cancelled target assignment when a task is reassigned back to a previous PIC', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);
    $branch = Branch::factory()->create();
    $oldPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $oldPic->syncBranchAccess([$branch->id], $branch->id);
    $currentPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $currentPic->syncBranchAccess([$branch->id], $branch->id);
    $cancelled = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id,
        'branch_id' => $branch->id,
        'user_id' => $oldPic->id,
        'status' => 'cancelled',
    ]);
    $current = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id,
        'branch_id' => $branch->id,
        'user_id' => $currentPic->id,
        'status' => 'in_progress',
    ]);

    $reactivated = app(AssignProjectTaskAction::class)->reassign($current, $oldPic->id);

    expect($reactivated->id)->toBe($cancelled->id)
        ->and($reactivated->status)->toBe(RndProjectTaskAssignmentStatus::Assigned)
        ->and($current->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::Cancelled)
        ->and($task->fresh()->assignments)->toHaveCount(2);
});
