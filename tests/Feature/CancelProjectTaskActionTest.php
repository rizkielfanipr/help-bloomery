<?php

use App\Actions\Rnd\ProjectTask\CancelProjectTaskAction;
use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;

it('cancels a task and cascades to every still-active assignment', function () {
    $task = RndProjectTask::factory()->create(['status' => 'in_progress']);
    $activeOne = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'in_progress']);
    $activeTwo = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'assigned']);
    $alreadyCancelled = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'cancelled']);

    $cancelled = app(CancelProjectTaskAction::class)->execute($task);

    expect($cancelled->status)->toBe(RndProjectTaskStatus::Cancelled)
        ->and($activeOne->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::Cancelled)
        ->and($activeTwo->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::Cancelled)
        ->and($alreadyCancelled->fresh()->status)->toBe(RndProjectTaskAssignmentStatus::Cancelled);
});

it('refuses to cancel a task that already reached a terminal status', function () {
    $task = RndProjectTask::factory()->create(['status' => 'completed']);

    expect(fn () => app(CancelProjectTaskAction::class)->execute($task))->toThrow(RuntimeException::class);
});
