<?php

use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->project = RndProject::query()->create([
        'name' => 'Croissant Launch', 'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
    ]);
});

it('hides the Kalender Tugas toggle from users without the view permission', function () {
    $outsider = User::factory()->create(['is_active' => true]);
    $outsider->givePermissionTo('view rnd projects');
    $this->actingAs($outsider);

    Livewire::test(ListProjects::class)->assertDontSee('Kalender Tugas');
});

it('shows the Kalender Tugas toggle and switches to the tasks view', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->givePermissionTo(['view rnd projects', 'view rnd project tasks']);
    $this->actingAs($manager);

    Livewire::test(ListProjects::class)
        ->assertSee('Kalender Tugas')
        ->call('showProjectTasks')
        ->assertSet('projectView', 'tasks')
        ->assertSee('Kalender Tugas');
});

it('creates a task from the modal with one PIC per branch and shows it on the calendar', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->givePermissionTo(['view rnd projects', 'view rnd project tasks', 'create rnd project tasks']);
    $this->actingAs($manager);

    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->syncBranchAccess([$branch->id], $branch->id);
    $manager->syncBranchAccess([$branch->id], $branch->id);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->set('taskCalendarMonth', '2026-10')
        ->call('openTaskModal', '2026-10-05')
        ->assertSet('taskAssignedDate', '2026-10-05')
        ->set('taskProjectId', (string) $this->project->id)
        ->set('taskTitle', 'Uji Rasa Croissant')
        ->set('taskCategory', 'tasting')
        ->set('taskDueDate', '2026-10-10')
        ->set('taskBranchRows.0.branch_id', (string) $branch->id)
        ->set('taskBranchRows.0.user_ids', [$pic->id])
        ->call('saveTask')
        ->assertHasNoErrors()
        ->assertSet('taskModalOpen', false)
        ->assertSee('Uji Rasa Croissant');

    $task = RndProjectTask::query()->where('title', 'Uji Rasa Croissant')->sole();
    expect($task->status->value)->toBe('assigned')
        ->and($task->assignments)->toHaveCount(1);
});

it('scopes the task calendar to branches the viewer can access', function () {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    $visibleTask = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Visible Task', 'due_date' => '2026-10-15']);
    $visibleTask->branches()->attach($ownBranch->id);
    $hiddenTask = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Hidden Task', 'due_date' => '2026-10-16']);
    $hiddenTask->branches()->attach($otherBranch->id);

    $viewer = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $viewer->givePermissionTo(['view rnd projects', 'view rnd project tasks']);
    $viewer->syncBranchAccess([$ownBranch->id], $ownBranch->id);
    $this->actingAs($viewer);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->set('taskCalendarMonth', '2026-10')
        ->assertSee('Visible Task')
        ->assertDontSee('Hidden Task');
});

it('lets a cross-branch viewer see tasks outside their own branch access', function () {
    $otherBranch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Cross Branch Task', 'due_date' => '2026-10-20']);
    $task->branches()->attach($otherBranch->id);

    $crossBranchViewer = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $crossBranchViewer->givePermissionTo(['view rnd projects', 'view rnd project tasks', 'view all branch rnd project tasks']);
    $this->actingAs($crossBranchViewer);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->set('taskCalendarMonth', '2026-10')
        ->assertSee('Cross Branch Task');
});

it('filters the task calendar by "Tugas Saya"', function () {
    $branch = Branch::factory()->create();
    $me = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $me->givePermissionTo(['view rnd projects', 'view rnd project tasks']);
    $me->syncBranchAccess([$branch->id], $branch->id);
    $this->actingAs($me);

    $myTask = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'My Task', 'due_date' => '2026-10-12']);
    $myTask->branches()->attach($branch->id);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $myTask->id, 'branch_id' => $branch->id, 'user_id' => $me->id]);

    $otherTask = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Other Task', 'due_date' => '2026-10-13']);
    $otherTask->branches()->attach($branch->id);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->set('taskCalendarMonth', '2026-10')
        ->assertSee('My Task')
        ->assertSee('Other Task')
        ->set('taskFilterMineOnly', true)
        ->assertSee('My Task')
        ->assertDontSee('Other Task');
});

it('opens the task detail and lets an authorized reviewer cancel it', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $manager->givePermissionTo(['view rnd projects', 'view rnd project tasks', 'cancel rnd project tasks']);
    $manager->syncBranchAccess([$branch->id], $branch->id);
    $this->actingAs($manager);

    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'status' => 'assigned']);
    $task->branches()->attach($branch->id);
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'status' => 'assigned']);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openTaskDetail', $task->id)
        ->assertSet('viewingTaskId', $task->id)
        ->call('cancelTask', $task->id);

    expect($task->fresh()->status->value)->toBe('cancelled');
});

it('blocks a branch-outsider from opening a task detail', function () {
    $branch = Branch::factory()->create();
    $outsider = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $outsider->givePermissionTo(['view rnd projects', 'view rnd project tasks']);
    $this->actingAs($outsider);

    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id]);
    $task->branches()->attach($branch->id);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openTaskDetail', $task->id)
        ->assertForbidden();
});

it('lets a PIC start, save progress, and submit a follow-up with an attachment', function () {
    Storage::fake('b2');

    $branch = Branch::factory()->create();
    $pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $pic->givePermissionTo(['view rnd projects', 'respond rnd project tasks']);
    $this->actingAs($pic);

    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'status' => 'assigned']);
    $task->branches()->attach($branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $pic->id, 'status' => 'assigned',
    ]);

    $page = Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openTaskDetail', $task->id)
        ->call('startAssignment', $assignment->id);

    expect($assignment->fresh()->status->value)->toBe('in_progress');

    $page->set('followUpNotes', 'Masih proses uji rasa.')
        ->call('saveFollowUp', $assignment->id, 'progress')
        ->assertHasNoErrors();

    expect($assignment->fresh()->followUps)->toHaveCount(1)
        ->and($assignment->fresh()->status->value)->toBe('in_progress');

    $page->set('followUpNotes', 'Selesai, siap direview.')
        ->set('followUpAttachments', [UploadedFile::fake()->image('hasil.jpg')])
        ->call('saveFollowUp', $assignment->id, 'submission')
        ->assertHasNoErrors();

    $fresh = $assignment->fresh();
    expect($fresh->status->value)->toBe('submitted')
        ->and($fresh->followUps)->toHaveCount(2)
        ->and($task->fresh()->status->value)->toBe('submitted');

    $submission = $fresh->followUps->firstWhere('follow_up_type', 'submission');
    expect($submission->result_attachments)->toHaveCount(1);
    Storage::disk('b2')->assertExists($submission->result_attachments[0]);
});

it('blocks a user from responding to another PIC\'s assignment', function () {
    $branch = Branch::factory()->create();
    $owner = User::factory()->create(['is_active' => true]);
    $intruder = User::factory()->create(['is_active' => true]);
    $intruder->givePermissionTo(['view rnd projects', 'respond rnd project tasks']);
    $this->actingAs($intruder);

    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'status' => 'assigned']);
    $task->branches()->attach($branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $owner->id, 'status' => 'assigned',
    ]);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('startAssignment', $assignment->id)
        ->assertForbidden();
});

it('shows the reviewer panel for a submitted assignment and lets an authorized reviewer approve it', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $reviewer->givePermissionTo(['view rnd projects', 'view rnd project tasks', 'review rnd project task follow ups']);
    $reviewer->syncBranchAccess([$branch->id], $branch->id);
    $this->actingAs($reviewer);

    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'status' => 'submitted']);
    $task->branches()->attach($branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'status' => 'submitted',
    ]);
    $assignment->followUps()->create([
        'follow_up_type' => 'submission', 'notes' => 'Hasil uji rasa sudah sesuai target.',
    ]);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openTaskDetail', $task->id)
        ->assertSee('Hasil uji rasa sudah sesuai target.')
        ->call('approveFollowUp', $assignment->id)
        ->assertHasNoErrors();

    expect($assignment->fresh()->status->value)->toBe('approved')
        ->and($task->fresh()->status->value)->toBe('completed');
});

it('requires a note before an authorized reviewer can request revision', function () {
    $branch = Branch::factory()->create();
    $reviewer = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $reviewer->givePermissionTo(['view rnd projects', 'view rnd project tasks', 'review rnd project task follow ups']);
    $reviewer->syncBranchAccess([$branch->id], $branch->id);
    $this->actingAs($reviewer);

    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'status' => 'submitted']);
    $task->branches()->attach($branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'status' => 'submitted',
    ]);

    $page = Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openTaskDetail', $task->id)
        ->call('requestRevision', $assignment->id)
        ->assertHasErrors(['reviewNote']);

    expect($assignment->fresh()->status->value)->toBe('submitted');

    $page->set('reviewNote', 'Tolong perbaiki tekstur adonan.')
        ->call('requestRevision', $assignment->id)
        ->assertHasNoErrors();

    expect($assignment->fresh()->status->value)->toBe('revision_required')
        ->and($task->fresh()->status->value)->toBe('revision_required');
});
