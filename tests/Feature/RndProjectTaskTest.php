<?php

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskFollowUp;
use App\Models\RndProjectTaskReminder;
use App\Models\User;
use App\Services\Rnd\ProjectTask\ProjectTaskAssigneeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('relates a task to its project, branches, and assignments', function () {
    $project = RndProject::query()->create([
        'name' => 'Croissant Launch', 'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
    ]);
    $branch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $project->id]);
    $task->branches()->attach($branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id,
        'branch_id' => $branch->id,
    ]);
    $followUp = RndProjectTaskFollowUp::factory()->create([
        'rnd_project_task_assignment_id' => $assignment->id,
    ]);
    $reminder = RndProjectTaskReminder::factory()->create([
        'rnd_project_task_assignment_id' => $assignment->id,
    ]);

    expect($task->project->is($project))->toBeTrue()
        ->and($task->branches->pluck('id'))->toContain($branch->id)
        ->and($task->assignments->pluck('id'))->toContain($assignment->id)
        ->and($assignment->task->is($task))->toBeTrue()
        ->and($assignment->branch->is($branch))->toBeTrue()
        ->and($followUp->assignment->is($assignment))->toBeTrue()
        ->and($reminder->assignment->is($assignment))->toBeTrue();
});

it('rejects a duplicate branch on the same task', function () {
    $task = RndProjectTask::factory()->create();
    $branch = Branch::factory()->create();
    $task->branches()->attach($branch->id);

    expect(fn () => $task->branches()->attach($branch->id))->toThrow(QueryException::class);
});

it('rejects a duplicate assignment for the same task, branch, and user', function () {
    $task = RndProjectTask::factory()->create();
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $user->id,
    ]);

    expect(fn () => RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $user->id,
    ]))->toThrow(QueryException::class);
});

it('rejects a duplicate reminder for the same assignment, type, and date', function () {
    $assignment = RndProjectTaskAssignment::factory()->create();
    RndProjectTaskReminder::factory()->create([
        'rnd_project_task_assignment_id' => $assignment->id,
        'reminder_type' => 'due_in_1_day',
        'reminder_date' => '2026-10-01',
    ]);

    expect(fn () => RndProjectTaskReminder::factory()->create([
        'rnd_project_task_assignment_id' => $assignment->id,
        'reminder_type' => 'due_in_1_day',
        'reminder_date' => '2026-10-01',
    ]))->toThrow(QueryException::class);
});

it('gates task creation, assignment, and cancellation behind their own permissions', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->givePermissionTo(['view rnd project tasks', 'create rnd project tasks', 'assign rnd project tasks', 'cancel rnd project tasks']);
    $outsider = User::factory()->create(['is_active' => true]);
    $task = RndProjectTask::factory()->create();

    expect($manager->can('create', RndProjectTask::class))->toBeTrue()
        ->and($manager->can('assign', $task))->toBeTrue()
        ->and($manager->can('cancel', $task))->toBeTrue()
        ->and($outsider->can('create', RndProjectTask::class))->toBeFalse()
        ->and($outsider->can('assign', $task))->toBeFalse()
        ->and($outsider->can('cancel', $task))->toBeFalse();
});

it('refuses to cancel a task that already reached a terminal status', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->givePermissionTo(['cancel rnd project tasks']);
    $completed = RndProjectTask::factory()->create(['status' => 'completed']);

    expect($manager->can('cancel', $completed))->toBeFalse();
});

it('scopes task visibility to branches the user can access, unless they hold cross-branch access', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create();
    $task->branches()->attach($branch->id);

    $branchUser = User::factory()->create(['is_active' => true]);
    $branchUser->givePermissionTo('view rnd project tasks');
    $branchUser->syncBranchAccess([$branch->id], $branch->id);

    $unrelatedUser = User::factory()->create(['is_active' => true]);
    $unrelatedUser->givePermissionTo('view rnd project tasks');
    $unrelatedUser->syncBranchAccess([$otherBranch->id], $otherBranch->id);

    $crossBranchViewer = User::factory()->create(['is_active' => true]);
    $crossBranchViewer->givePermissionTo(['view rnd project tasks', 'view all branch rnd project tasks']);

    expect($branchUser->can('view', $task))->toBeTrue()
        ->and($unrelatedUser->can('view', $task))->toBeFalse()
        ->and($crossBranchViewer->can('view', $task))->toBeTrue();
});

it('lets an assignee view their own task even without branch access', function () {
    $branch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create();
    $task->branches()->attach($branch->id);

    $pic = User::factory()->create(['is_active' => true]);
    $pic->givePermissionTo('view rnd project tasks');
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $pic->id,
    ]);

    expect($pic->can('view', $task))->toBeTrue();
});

it('lets an assignee view their own task even without the view rnd project tasks permission', function () {
    $branch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create();
    $task->branches()->attach($branch->id);

    $pic = User::factory()->create(['is_active' => true]);
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $pic->id,
    ]);

    expect($pic->can('view', $task))->toBeTrue();
});

it('only lets the assignment owner respond, even for another user with the same permission', function () {
    $owner = User::factory()->create(['is_active' => true]);
    $owner->givePermissionTo('respond rnd project tasks');
    $otherUser = User::factory()->create(['is_active' => true]);
    $otherUser->givePermissionTo('respond rnd project tasks');
    $assignment = RndProjectTaskAssignment::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('respond', $assignment))->toBeTrue()
        ->and($otherUser->can('respond', $assignment))->toBeFalse();
});

it('gates follow-up review behind its own permission', function () {
    $reviewer = User::factory()->create(['is_active' => true]);
    $reviewer->givePermissionTo('review rnd project task follow ups');
    $pic = User::factory()->create(['is_active' => true]);
    $assignment = RndProjectTaskAssignment::factory()->create(['user_id' => $pic->id]);

    expect($reviewer->can('review', $assignment))->toBeTrue()
        ->and($pic->can('review', $assignment))->toBeFalse();
});

it('excludes SUPERADMIN and access_all_branches users from PIC eligibility', function () {
    $branch = Branch::factory()->create();

    $eligible = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $eligible->syncBranchAccess([$branch->id], $branch->id);

    $superadmin = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $superadmin->assignRole('SUPERADMIN');
    $superadmin->syncBranchAccess([$branch->id], $branch->id);

    $allBranchUser = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $allBranchUser->syncBranchAccess([$branch->id], $branch->id);

    $resolver = app(ProjectTaskAssigneeResolver::class);
    $eligibleIds = $resolver->eligibleUsersForBranch($branch->id)->pluck('id');

    expect($eligibleIds)->toContain($eligible->id)
        ->not->toContain($superadmin->id)
        ->not->toContain($allBranchUser->id)
        ->and($resolver->isEligible($superadmin, $branch->id))->toBeFalse()
        ->and($resolver->isEligible($allBranchUser, $branch->id))->toBeFalse();
});

it('excludes inactive users from PIC eligibility even with branch access', function () {
    $branch = Branch::factory()->create();
    $inactive = User::factory()->create(['is_active' => false, 'access_all_branches' => false]);
    $inactive->syncBranchAccess([$branch->id], $branch->id);

    $resolver = app(ProjectTaskAssigneeResolver::class);

    expect($resolver->eligibleUsersForBranch($branch->id)->pluck('id'))->not->toContain($inactive->id)
        ->and($resolver->isEligible($inactive, $branch->id))->toBeFalse();
});

it('excludes a user without access to the branch from PIC eligibility', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $user->syncBranchAccess([$otherBranch->id], $otherBranch->id);

    $resolver = app(ProjectTaskAssigneeResolver::class);

    expect($resolver->eligibleUsersForBranch($branch->id)->pluck('id'))->not->toContain($user->id)
        ->and($resolver->isEligible($user, $branch->id))->toBeFalse();
});

it('rejects a deadline earlier than the assign date', function () {
    $invalid = RndProjectTask::factory()->make([
        'assigned_date' => '2026-10-10', 'due_date' => '2026-10-05',
    ]);
    $valid = RndProjectTask::factory()->make([
        'assigned_date' => '2026-10-10', 'due_date' => '2026-10-10',
    ]);

    expect($invalid->hasValidDeadline())->toBeFalse()
        ->and($valid->hasValidDeadline())->toBeTrue();
});

it('excludes cancelled assignments from a task\'s active assignments', function () {
    $task = RndProjectTask::factory()->create();
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'status' => RndProjectTaskAssignmentStatus::Cancelled->value,
    ]);
    $active = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'status' => RndProjectTaskAssignmentStatus::InProgress->value,
    ]);

    expect($task->activeAssignments->pluck('id'))->toHaveCount(1)
        ->and($task->activeAssignments->pluck('id'))->toContain($active->id);
});
