<?php

use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateApplication;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = RndProject::query()->create([
        'name' => 'Croissant Launch', 'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
    ]);
});

it('registers the new template and copy permissions for RND_STAFF', function () {
    $staff = User::factory()->create();
    $staff->assignRole('RND_STAFF');

    expect($staff->can('view rnd project task templates'))->toBeTrue()
        ->and($staff->can('manage rnd project task templates'))->toBeTrue()
        ->and($staff->can('apply rnd project task templates'))->toBeTrue()
        ->and($staff->can('copy rnd project tasks'))->toBeTrue();
});

it('lets template viewers list but not manage templates', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view rnd project task templates');
    $template = RndProjectTaskTemplate::factory()->create();

    expect($viewer->can('viewAny', RndProjectTaskTemplate::class))->toBeTrue()
        ->and($viewer->can('create', RndProjectTaskTemplate::class))->toBeFalse()
        ->and($viewer->can('update', $template))->toBeFalse();
});

it('blocks deleting a template that was already applied', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo('manage rnd project task templates');
    $unused = RndProjectTaskTemplate::factory()->create();
    $used = RndProjectTaskTemplate::factory()->create();
    RndProjectTaskTemplateApplication::factory()->create(['rnd_project_task_template_id' => $used->id]);

    expect($manager->can('delete', $unused))->toBeTrue()
        ->and($manager->can('delete', $used))->toBeFalse();
});

it('does not let managing templates grant task creation', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo(['manage rnd project task templates', 'apply rnd project task templates']);

    expect($manager->can('applyTemplate', [RndProjectTask::class, $this->project]))->toBeFalse();

    $manager->givePermissionTo('create rnd project tasks');

    expect($manager->fresh()->can('applyTemplate', [RndProjectTask::class, $this->project]))->toBeTrue();
});

it('requires both copy and create permissions plus task visibility to copy', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id]);
    $task->branches()->attach($branch->id);

    $user = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $user->syncBranchAccess([$otherBranch->id], $otherBranch->id);
    $user->givePermissionTo(['view rnd project tasks', 'copy rnd project tasks']);

    expect($user->can('copy', $task))->toBeFalse();

    $user->givePermissionTo('create rnd project tasks');
    expect($user->fresh()->can('copy', $task))->toBeFalse();

    $user->syncBranchAccess([$branch->id], $branch->id);
    expect($user->fresh()->can('copy', $task->fresh()))->toBeTrue();
});

it('treats archived projects as read-only for create, apply, copy, edit, and assign', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'view rnd project tasks', 'view all branch rnd project tasks', 'create rnd project tasks',
        'update rnd project tasks', 'assign rnd project tasks', 'copy rnd project tasks',
        'apply rnd project task templates',
    ]);
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id]);

    expect($user->can('createForProject', [RndProjectTask::class, $this->project]))->toBeTrue()
        ->and($user->can('update', $task))->toBeTrue();

    $this->project->delete();
    $task = $task->fresh();

    expect($user->can('createForProject', [RndProjectTask::class, $this->project]))->toBeFalse()
        ->and($user->can('applyTemplate', [RndProjectTask::class, $this->project]))->toBeFalse()
        ->and($user->can('copy', $task))->toBeFalse()
        ->and($user->can('update', $task))->toBeFalse()
        ->and($user->can('assign', $task))->toBeFalse()
        ->and($user->can('view', $task))->toBeTrue();
});
