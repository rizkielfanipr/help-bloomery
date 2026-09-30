<?php

use App\Models\Branch;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('b2');
});

it('lets an authorized PIC download their own result attachment', function () {
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true]);
    $task = RndProjectTask::factory()->create();
    $task->branches()->attach($branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $pic->id,
    ]);

    $path = "rnd/project-tasks/{$task->id}/assignments/{$assignment->id}/results/hasil.jpg";
    Storage::disk('b2')->put($path, 'fake-image-content');

    $this->actingAs($pic)
        ->get(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $path]))
        ->assertOk();
});

it('blocks a user with no relation to the task', function () {
    $branch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create();
    $task->branches()->attach($branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id,
    ]);

    $path = "rnd/project-tasks/{$task->id}/assignments/{$assignment->id}/results/hasil.jpg";
    Storage::disk('b2')->put($path, 'fake-image-content');

    $outsider = User::factory()->create(['is_active' => true]);

    $this->actingAs($outsider)
        ->get(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $path]))
        ->assertForbidden();
});

it('rejects a path that does not match the expected attachment pattern', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => 'rnd/project-tasks/secrets.env']))
        ->assertNotFound();
});

it('returns 404 when the file itself does not exist on disk', function () {
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true]);
    $task = RndProjectTask::factory()->create();
    $task->branches()->attach($branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $pic->id,
    ]);

    $path = "rnd/project-tasks/{$task->id}/assignments/{$assignment->id}/results/missing.jpg";

    $this->actingAs($pic)
        ->get(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $path]))
        ->assertNotFound();
});
