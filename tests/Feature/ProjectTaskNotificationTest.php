<?php

use App\Actions\Rnd\ProjectTask\AssignProjectTaskAction;
use App\Actions\Rnd\ProjectTask\CancelProjectTaskAction;
use App\Actions\Rnd\ProjectTask\CreateProjectTaskAction;
use App\Actions\Rnd\ProjectTask\ReviewProjectTaskFollowUpAction;
use App\Actions\Rnd\ProjectTask\SubmitProjectTaskFollowUpAction;
use App\Actions\Rnd\ProjectTask\UpdateProjectTaskAction;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use App\Notifications\ProjectTaskApprovedNotification;
use App\Notifications\ProjectTaskAssignedNotification;
use App\Notifications\ProjectTaskCancelledNotification;
use App\Notifications\ProjectTaskDeadlineChangedNotification;
use App\Notifications\ProjectTaskFollowUpSubmittedNotification;
use App\Notifications\ProjectTaskRevisionRequestedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    $this->project = RndProject::query()->create([
        'name' => 'Notification Test Project', 'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
    ]);
});

it('notifies the PIC when a task is created and shared', function () {
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);
    $pic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    app(CreateProjectTaskAction::class)->execute($this->project, [
        'title' => 'Notif Task', 'task_type' => 'general', 'description' => null,
        'assigned_date' => '2026-10-01', 'due_date' => '2026-10-05', 'priority' => 'medium',
        'instruction_attachments' => null,
        'branches' => [['branch_id' => $branch->id, 'user_id' => $pic->id]],
    ], User::factory()->create());

    Notification::assertSentTo($pic, ProjectTaskAssignedNotification::class);
});

it('notifies a newly-assigned PIC via AssignProjectTaskAction', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);
    $pic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    app(AssignProjectTaskAction::class)->execute($task, $branch->id, $pic->id);

    Notification::assertSentTo($pic, ProjectTaskAssignedNotification::class);
});

it('notifies the new PIC (not the old one) on reassign', function () {
    $branch = Branch::factory()->create();
    $oldPic = User::factory()->create(['is_active' => false]);
    $assignment = RndProjectTaskAssignment::factory()->create(['branch_id' => $branch->id, 'user_id' => $oldPic->id, 'status' => 'in_progress']);
    $newPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $newPic->syncBranchAccess([$branch->id], $branch->id);
    $newPic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    app(AssignProjectTaskAction::class)->reassign($assignment, $newPic->id);

    Notification::assertSentTo($newPic, ProjectTaskAssignedNotification::class);
    Notification::assertNotSentTo($oldPic, ProjectTaskAssignedNotification::class);
});

it('notifies every active PIC when the deadline changes', function () {
    $task = RndProjectTask::factory()->create(['status' => 'in_progress', 'due_date' => '2026-10-05']);
    $activePic = User::factory()->create();
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'user_id' => $activePic->id, 'status' => 'in_progress']);
    $cancelledPic = User::factory()->create();
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'user_id' => $cancelledPic->id, 'status' => 'cancelled']);

    app(UpdateProjectTaskAction::class)->execute($task, [
        'title' => $task->title, 'task_type' => $task->task_type, 'description' => null,
        'assigned_date' => $task->assigned_date->toDateString(), 'due_date' => '2026-10-12',
        'priority' => 'medium', 'instruction_attachments' => null,
    ]);

    Notification::assertSentTo($activePic, ProjectTaskDeadlineChangedNotification::class);
    Notification::assertNotSentTo($cancelledPic, ProjectTaskDeadlineChangedNotification::class);
});

it('does not notify when the task is updated without changing the deadline', function () {
    $task = RndProjectTask::factory()->create(['status' => 'in_progress', 'due_date' => '2026-10-05']);
    $pic = User::factory()->create();
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'user_id' => $pic->id, 'status' => 'in_progress']);

    app(UpdateProjectTaskAction::class)->execute($task, [
        'title' => 'Renamed', 'task_type' => $task->task_type, 'description' => null,
        'assigned_date' => $task->assigned_date->toDateString(), 'due_date' => '2026-10-05',
        'priority' => 'medium', 'instruction_attachments' => null,
    ]);

    Notification::assertNotSentTo($pic, ProjectTaskDeadlineChangedNotification::class);
});

it('notifies eligible reviewers when a PIC submits for review', function () {
    $branch = Branch::factory()->create();
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['branch_id' => $branch->id, 'user_id' => $pic->id, 'status' => 'in_progress']);

    $reviewer = User::factory()->create(['is_active' => true]);
    $reviewer->givePermissionTo('review rnd project task follow ups');
    $reviewer->syncBranchAccess([$branch->id], $branch->id);

    app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
        'follow_up_type' => 'submission', 'notes' => 'Selesai.', 'estimated_completion_date' => null, 'result_attachments' => null,
    ], $pic);

    Notification::assertSentTo($reviewer, ProjectTaskFollowUpSubmittedNotification::class);
});

it('does not notify anyone on a progress-only follow-up', function () {
    $branch = Branch::factory()->create();
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['branch_id' => $branch->id, 'user_id' => $pic->id, 'status' => 'in_progress']);
    $reviewer = User::factory()->create(['is_active' => true]);
    $reviewer->givePermissionTo('review rnd project task follow ups');
    $reviewer->syncBranchAccess([$branch->id], $branch->id);

    app(SubmitProjectTaskFollowUpAction::class)->execute($assignment, [
        'follow_up_type' => 'progress', 'notes' => 'Masih proses.', 'estimated_completion_date' => null, 'result_attachments' => null,
    ], $pic);

    Notification::assertNothingSent();
});

it('notifies the PIC on approval', function () {
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['user_id' => $pic->id, 'status' => 'submitted']);

    app(ReviewProjectTaskFollowUpAction::class)->execute($assignment, 'approve', null, User::factory()->create());

    Notification::assertSentTo($pic, ProjectTaskApprovedNotification::class);
});

it('notifies the PIC on revision request', function () {
    $pic = User::factory()->create();
    $assignment = RndProjectTaskAssignment::factory()->create(['user_id' => $pic->id, 'status' => 'submitted']);

    app(ReviewProjectTaskFollowUpAction::class)->execute($assignment, 'revision', 'Perlu perbaikan.', User::factory()->create());

    Notification::assertSentTo($pic, ProjectTaskRevisionRequestedNotification::class);
});

it('notifies every affected PIC when a task is cancelled', function () {
    $task = RndProjectTask::factory()->create(['status' => 'in_progress']);
    $activePic = User::factory()->create();
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'user_id' => $activePic->id, 'status' => 'in_progress']);

    app(CancelProjectTaskAction::class)->execute($task);

    Notification::assertSentTo($activePic, ProjectTaskCancelledNotification::class);
});
