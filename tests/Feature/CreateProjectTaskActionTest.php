<?php

use App\Actions\Rnd\ProjectTask\CreateProjectTaskAction;
use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->project = RndProject::query()->create([
        'name' => 'Croissant Launch', 'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
    ]);
    $this->actor = User::factory()->create();
});

it('creates a task already Assigned with one assignment per branch and PIC pair', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $picA = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $picA->syncBranchAccess([$branchA->id], $branchA->id);
    $picA->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));
    $picB = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $picB->syncBranchAccess([$branchB->id], $branchB->id);
    $picB->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    $task = app(CreateProjectTaskAction::class)->execute($this->project, [
        'title' => 'Uji Rasa Croissant',
        'task_type' => 'tasting',
        'description' => 'Coba rasa batch baru.',
        'assigned_date' => '2026-10-01',
        'due_date' => '2026-10-05',
        'priority' => 'high',
        'instruction_attachments' => null,
        'branches' => [
            ['branch_id' => $branchA->id, 'user_id' => $picA->id],
            ['branch_id' => $branchB->id, 'user_id' => $picB->id],
        ],
    ], $this->actor);

    expect($task->status)->toBe(RndProjectTaskStatus::Assigned)
        ->and($task->branches->pluck('id')->sort()->values()->all())->toBe([$branchA->id, $branchB->id])
        ->and($task->assignments)->toHaveCount(2)
        ->and($task->assignments->pluck('status')->unique()->all())->toBe([RndProjectTaskAssignmentStatus::Assigned]);
});

it('allows more than one PIC on the same branch', function () {
    $branch = Branch::factory()->create();
    $picOne = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $picOne->syncBranchAccess([$branch->id], $branch->id);
    $picOne->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));
    $picTwo = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $picTwo->syncBranchAccess([$branch->id], $branch->id);
    $picTwo->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    $task = app(CreateProjectTaskAction::class)->execute($this->project, [
        'title' => 'Quality Control Batch', 'task_type' => 'quality_control', 'description' => null,
        'assigned_date' => '2026-10-01', 'due_date' => '2026-10-02', 'priority' => 'medium',
        'instruction_attachments' => null,
        'branches' => [
            ['branch_id' => $branch->id, 'user_id' => $picOne->id],
            ['branch_id' => $branch->id, 'user_id' => $picTwo->id],
        ],
    ], $this->actor);

    expect($task->assignments)->toHaveCount(2)
        ->and($task->branches)->toHaveCount(1);
});

it('rejects a deadline earlier than the assign date', function () {
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);
    $pic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    expect(fn () => app(CreateProjectTaskAction::class)->execute($this->project, [
        'title' => 'Invalid Deadline', 'task_type' => 'general', 'description' => null,
        'assigned_date' => '2026-10-10', 'due_date' => '2026-10-05', 'priority' => 'medium',
        'instruction_attachments' => null,
        'branches' => [['branch_id' => $branch->id, 'user_id' => $pic->id]],
    ], $this->actor))->toThrow(ValidationException::class);
});

it('rejects a PIC who cannot access the chosen branch', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $ineligiblePic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $ineligiblePic->syncBranchAccess([$otherBranch->id], $otherBranch->id);
    $ineligiblePic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    expect(fn () => app(CreateProjectTaskAction::class)->execute($this->project, [
        'title' => 'Wrong Branch PIC', 'task_type' => 'general', 'description' => null,
        'assigned_date' => '2026-10-01', 'due_date' => '2026-10-05', 'priority' => 'medium',
        'instruction_attachments' => null,
        'branches' => [['branch_id' => $branch->id, 'user_id' => $ineligiblePic->id]],
    ], $this->actor))->toThrow(ValidationException::class);
});

it('rejects a task with no branches', function () {
    expect(fn () => app(CreateProjectTaskAction::class)->execute($this->project, [
        'title' => 'No Branch', 'task_type' => 'general', 'description' => null,
        'assigned_date' => '2026-10-01', 'due_date' => '2026-10-05', 'priority' => 'medium',
        'instruction_attachments' => null,
        'branches' => [],
    ], $this->actor))->toThrow(ValidationException::class);
});

it('rejects the same PIC listed twice for the same branch before creating a task', function () {
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);
    $pic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    expect(fn () => app(CreateProjectTaskAction::class)->execute($this->project, [
        'title' => 'Duplicate PIC',
        'task_type' => 'general',
        'description' => null,
        'assigned_date' => '2026-10-01',
        'due_date' => '2026-10-05',
        'priority' => 'medium',
        'instruction_attachments' => null,
        'branches' => [
            ['branch_id' => $branch->id, 'user_id' => $pic->id],
            ['branch_id' => $branch->id, 'user_id' => $pic->id],
        ],
    ], $this->actor))->toThrow(ValidationException::class);

    expect($this->project->tasks()->count())->toBe(0);
});
