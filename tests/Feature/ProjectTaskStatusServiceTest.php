<?php

use App\Enums\RndProjectTaskStatus;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Services\Rnd\ProjectTask\ProjectTaskStatusService;

it('moves the task to In Progress once at least one assignment starts', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'in_progress']);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'assigned']);

    $synced = app(ProjectTaskStatusService::class)->sync($task);

    expect($synced->status)->toBe(RndProjectTaskStatus::InProgress);
});

it('moves the task to Submitted only once every active assignment is submitted', function () {
    $task = RndProjectTask::factory()->create(['status' => 'in_progress']);
    $a = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'submitted']);
    $b = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'in_progress']);

    expect(app(ProjectTaskStatusService::class)->sync($task)->status)->toBe(RndProjectTaskStatus::InProgress);

    $b->update(['status' => 'submitted']);
    expect(app(ProjectTaskStatusService::class)->sync($task->fresh())->status)->toBe(RndProjectTaskStatus::Submitted);
});

it('moves the task to Completed only once every active assignment is approved', function () {
    $task = RndProjectTask::factory()->create(['status' => 'submitted']);
    $a = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'approved']);
    $b = RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'submitted']);

    expect(app(ProjectTaskStatusService::class)->sync($task)->status)->toBe(RndProjectTaskStatus::Submitted);

    $b->update(['status' => 'approved']);
    $synced = app(ProjectTaskStatusService::class)->sync($task->fresh());

    expect($synced->status)->toBe(RndProjectTaskStatus::Completed)
        ->and($synced->completed_at)->not->toBeNull();
});

it('moves the task to Revision Required if any active assignment needs revision', function () {
    $task = RndProjectTask::factory()->create(['status' => 'submitted']);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'approved']);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'revision_required']);

    expect(app(ProjectTaskStatusService::class)->sync($task)->status)->toBe(RndProjectTaskStatus::RevisionRequired);
});

it('ignores cancelled assignments when aggregating status', function () {
    $task = RndProjectTask::factory()->create(['status' => 'submitted']);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'approved']);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'cancelled']);

    expect(app(ProjectTaskStatusService::class)->sync($task)->status)->toBe(RndProjectTaskStatus::Completed);
});

it('never reopens a task that already reached a terminal status', function () {
    $task = RndProjectTask::factory()->create(['status' => 'completed']);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'status' => 'revision_required']);

    expect(app(ProjectTaskStatusService::class)->sync($task)->status)->toBe(RndProjectTaskStatus::Completed);
});
