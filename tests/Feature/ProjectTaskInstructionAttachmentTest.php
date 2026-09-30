<?php

use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Storage::fake('b2');

    $this->manager = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->manager->assignRole('SUPERADMIN');
    $this->actingAs($this->manager);

    $this->project = RndProject::query()->create([
        'name' => 'Attachment Test Project', 'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
    ]);
});

it('uploads an instruction attachment when creating a task and stores it under the new task id', function () {
    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openTaskModal')
        ->set('taskProjectId', (string) $this->project->id)
        ->set('taskTitle', 'Task With Attachment')
        ->set('taskCategory', 'tasting')
        ->set('taskAssignedDate', '2026-10-01')
        ->set('taskDueDate', '2026-10-05')
        ->set('taskBranchRows.0.branch_id', (string) $branch->id)
        ->set('taskBranchRows.0.user_ids', [$pic->id])
        ->set('taskInstructionAttachments', [UploadedFile::fake()->image('instruksi.jpg')])
        ->call('saveTask')
        ->assertHasNoErrors();

    $task = RndProjectTask::query()->where('title', 'Task With Attachment')->sole();
    expect($task->instruction_attachments)->toHaveCount(1);
    Storage::disk('b2')->assertExists($task->instruction_attachments[0]);
    expect($task->instruction_attachments[0])->toStartWith("rnd/project-tasks/{$task->id}/instructions/");
});

it('preserves existing instruction attachments when editing a task without uploading new ones', function () {
    $task = RndProjectTask::factory()->create([
        'rnd_project_id' => $this->project->id,
        'instruction_attachments' => ['rnd/project-tasks/999/instructions/existing.jpg'],
    ]);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openEditTaskModal', $task->id)
        ->set('taskTitle', 'Renamed Task')
        ->call('saveTask')
        ->assertHasNoErrors();

    expect($task->fresh()->instruction_attachments)->toBe(['rnd/project-tasks/999/instructions/existing.jpg']);
});

it('appends a new instruction attachment when editing without deleting the old one', function () {
    Storage::disk('b2')->put('rnd/project-tasks/999/instructions/existing.jpg', 'x');
    $task = RndProjectTask::factory()->create([
        'rnd_project_id' => $this->project->id,
        'instruction_attachments' => ['rnd/project-tasks/999/instructions/existing.jpg'],
    ]);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openEditTaskModal', $task->id)
        ->set('taskInstructionAttachments', [UploadedFile::fake()->image('baru.jpg')])
        ->call('saveTask')
        ->assertHasNoErrors();

    $fresh = $task->fresh();
    expect($fresh->instruction_attachments)->toHaveCount(2)
        ->and($fresh->instruction_attachments[0])->toBe('rnd/project-tasks/999/instructions/existing.jpg');
    Storage::disk('b2')->assertExists('rnd/project-tasks/999/instructions/existing.jpg');
});

it('removes one instruction attachment and deletes the underlying file', function () {
    Storage::disk('b2')->put('rnd/project-tasks/999/instructions/a.jpg', 'a');
    Storage::disk('b2')->put('rnd/project-tasks/999/instructions/b.jpg', 'b');
    $task = RndProjectTask::factory()->create([
        'rnd_project_id' => $this->project->id,
        'instruction_attachments' => ['rnd/project-tasks/999/instructions/a.jpg', 'rnd/project-tasks/999/instructions/b.jpg'],
    ]);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('removeInstructionAttachment', $task->id, 0);

    expect($task->fresh()->instruction_attachments)->toBe(['rnd/project-tasks/999/instructions/b.jpg']);
    Storage::disk('b2')->assertMissing('rnd/project-tasks/999/instructions/a.jpg');
    Storage::disk('b2')->assertExists('rnd/project-tasks/999/instructions/b.jpg');
});

it('lets an authorized viewer download an instruction attachment', function () {
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id]);
    $path = "rnd/project-tasks/{$task->id}/instructions/instruksi.jpg";
    $task->update(['instruction_attachments' => [$path]]);
    Storage::disk('b2')->put($path, 'content');

    $this->get(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $path]))
        ->assertOk();
});

it('blocks a user without view access from downloading an instruction attachment', function () {
    $branch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id]);
    $task->branches()->attach($branch->id);
    $path = "rnd/project-tasks/{$task->id}/instructions/instruksi.jpg";
    $task->update(['instruction_attachments' => [$path]]);
    Storage::disk('b2')->put($path, 'content');

    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider);

    $this->get(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $path]))
        ->assertForbidden();
});

it('rejects an instruction file that exists but is not registered on the task', function () {
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id]);
    $path = "rnd/project-tasks/{$task->id}/instructions/unregistered.jpg";
    Storage::disk('b2')->put($path, 'content');

    $this->get(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $path]))
        ->assertNotFound();
});

it('rejects uploads that would make the total instruction attachments exceed five', function () {
    $task = RndProjectTask::factory()->create([
        'rnd_project_id' => $this->project->id,
        'instruction_attachments' => [
            'rnd/project-tasks/999/instructions/1.jpg',
            'rnd/project-tasks/999/instructions/2.jpg',
            'rnd/project-tasks/999/instructions/3.jpg',
            'rnd/project-tasks/999/instructions/4.jpg',
        ],
    ]);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openEditTaskModal', $task->id)
        ->set('taskInstructionAttachments', [
            UploadedFile::fake()->image('5.jpg'),
            UploadedFile::fake()->image('6.jpg'),
        ])
        ->call('saveTask')
        ->assertHasErrors(['taskInstructionAttachments']);

    expect($task->fresh()->instruction_attachments)->toHaveCount(4);
});
