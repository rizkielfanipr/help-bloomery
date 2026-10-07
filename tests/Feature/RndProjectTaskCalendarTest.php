<?php

use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Filament\Helpdesk\Resources\Projects\Pages\ViewProject;
use App\Livewire\RndProjectTaskCalendar;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateApplication;
use App\Models\RndProjectTaskTemplateCheckpoint;
use App\Models\User;
use App\Notifications\ProjectTaskAssignedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->project = RndProject::query()->create([
        'name' => 'Seasonal Menu', 'start_date' => '2026-10-01', 'end_date' => '2026-10-30',
    ]);
    $this->otherProject = RndProject::query()->create([
        'name' => 'Other Project', 'start_date' => '2026-10-01', 'end_date' => '2026-12-01',
    ]);

    $this->branch = Branch::factory()->create();
    $this->pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $this->pic->syncBranchAccess([$this->branch->id], $this->branch->id);
    $this->pic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->givePermissionTo([
        'access backoffice', 'view rnd projects', 'view rnd project tasks', 'view all branch rnd project tasks',
        'create rnd project tasks', 'update rnd project tasks', 'copy rnd project tasks', 'apply rnd project task templates',
    ]);

    $this->taskFor = function (RndProject $project, string $dueDate, array $attributes = []): RndProjectTask {
        $task = RndProjectTask::factory()->create(array_merge([
            'rnd_project_id' => $project->id, 'status' => 'assigned',
            'assigned_date' => $dueDate, 'due_date' => $dueDate,
        ], $attributes));
        $task->branches()->attach($this->branch->id);

        return $task;
    };

    $this->calendar = fn (?RndProject $project = null) => Livewire::test(RndProjectTaskCalendar::class, ['projectId' => ($project ?? $this->project)->id]);

    $this->visibleTaskIds = fn ($component): array => collect($component->instance()->calendarDays())
        ->flatMap(fn (array $day): array => $day['tasks'])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();
});

it('embeds the Kalender & Task section on the project detail page for task viewers only', function () {
    $this->actingAs($this->manager);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertSeeLivewire(RndProjectTaskCalendar::class);

    $projectOnly = User::factory()->create(['is_active' => true]);
    $projectOnly->givePermissionTo(['access backoffice', 'view rnd projects']);
    $this->actingAs($projectOnly);

    Livewire::test(ViewProject::class, ['record' => $this->project->id])
        ->assertDontSeeLivewire(RndProjectTaskCalendar::class);
});

it('only shows tasks of the active project within the visible grid, including the edge days', function () {
    $this->actingAs($this->manager);

    $firstGridDay = ($this->taskFor)($this->project, '2026-09-28', ['title' => 'Grid Start']);
    $lastGridDay = ($this->taskFor)($this->project, '2026-11-01', ['title' => 'Grid End']);
    $midMonth = ($this->taskFor)($this->project, '2026-10-15', ['title' => 'Mid Month']);
    ($this->taskFor)($this->project, '2026-09-27', ['title' => 'Before Grid']);
    ($this->taskFor)($this->project, '2026-11-02', ['title' => 'After Grid']);
    ($this->taskFor)($this->otherProject, '2026-10-15', ['title' => 'Other Project Task']);

    $component = ($this->calendar)()
        ->assertSee('Mid Month')
        ->assertDontSee('Other Project Task')
        ->assertDontSee('Before Grid');

    expect(($this->visibleTaskIds)($component))->toBe(collect([$firstGridDay->id, $midMonth->id, $lastGridDay->id])->sort()->values()->all());
});

it('respects branch scope for users without all-branch access', function () {
    $outsiderBranch = Branch::factory()->create();
    $viewer = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $viewer->syncBranchAccess([$outsiderBranch->id], $outsiderBranch->id);
    $viewer->givePermissionTo(['view rnd project tasks']);
    $this->actingAs($viewer);

    ($this->taskFor)($this->project, '2026-10-15', ['title' => 'Hidden Branch Task']);
    $visible = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Own Branch Task', 'due_date' => '2026-10-16']);
    $visible->branches()->attach($outsiderBranch->id);

    $component = ($this->calendar)()->assertSee('Own Branch Task')->assertDontSee('Hidden Branch Task');

    expect(($this->visibleTaskIds)($component))->toBe([$visible->id]);
});

it('navigates between months and refreshes the dataset', function () {
    $this->actingAs($this->manager);
    ($this->taskFor)($this->project, '2026-12-10', ['title' => 'December Task']);

    ($this->calendar)()
        ->assertSet('calendarMonth', '2026-10')
        ->assertDontSee('December Task')
        ->call('nextMonth')
        ->call('nextMonth')
        ->assertSet('calendarMonth', '2026-12')
        ->assertSee('December Task')
        ->call('previousMonth')
        ->assertSet('calendarMonth', '2026-11')
        ->call('currentMonth')
        ->assertSet('calendarMonth', '2026-10')
        ->set('calendarMonth', 'not-a-month')
        ->assertSet('calendarMonth', '2026-10');
});

it('feeds the desktop grid and the mobile agenda from the same dataset', function () {
    $this->actingAs($this->manager);
    $tasks = collect(['2026-10-05', '2026-10-05', '2026-10-20', '2026-10-31'])
        ->map(fn (string $date) => ($this->taskFor)($this->project, $date));

    $component = ($this->calendar)();
    $gridIds = collect($component->viewData('weeks'))->flatten(1)->flatMap(fn (array $day): array => $day['tasks'])->pluck('id')->sort()->values()->all();
    $agendaIds = collect($component->viewData('agendaDays'))->flatMap(fn (array $day): array => $day['tasks'])->pluck('id')->sort()->values()->all();

    expect($gridIds)->toBe($tasks->pluck('id')->sort()->values()->all())
        ->and($agendaIds)->toBe($gridIds)
        ->and(collect($component->viewData('agendaDays'))->contains(fn (array $day): bool => $day['isRelease']))->toBeTrue();
});

it('creates a task locked to the active project from a clicked date', function () {
    Notification::fake();
    $this->actingAs($this->manager);

    ($this->calendar)()
        ->call('openTaskModal', '2026-10-20')
        ->assertSet('taskAssignedDate', '2026-10-20')
        ->assertSet('taskProjectId', (string) $this->project->id)
        ->assertSee('Seasonal Menu')
        ->set('taskProjectId', (string) $this->otherProject->id)
        ->set('taskTitle', 'Uji Rasa Lokal')
        ->set('taskCategory', 'tasting')
        ->set('taskDueDate', '2026-10-22')
        ->set('taskBranchRows', [['branch_id' => (string) $this->branch->id, 'user_ids' => [(string) $this->pic->id]]])
        ->call('saveTask')
        ->assertHasNoErrors()
        ->assertSet('taskModalOpen', false)
        ->assertSee('Uji Rasa Lokal');

    $task = RndProjectTask::query()->where('title', 'Uji Rasa Lokal')->sole();

    expect($task->rnd_project_id)->toBe($this->project->id);
    Notification::assertSentTo($this->pic, ProjectTaskAssignedNotification::class);
});

it('shows PICs as a checkbox list that follows the chosen branch', function () {
    $this->actingAs($this->manager);
    $emptyBranch = Branch::factory()->create(['is_active' => true]);

    ($this->calendar)()
        ->call('openTaskModal', '2026-10-20')
        ->assertSee('Pilih Branch terlebih dahulu.')
        ->assertDontSee('PIC (bisa lebih dari satu)')
        ->set('taskBranchRows.0.branch_id', (string) $this->branch->id)
        ->assertSee($this->pic->display_username)
        ->assertSeeHtml('type="checkbox" value="'.$this->pic->id.'" wire:model="taskBranchRows.0.user_ids"')
        ->set('taskBranchRows.0.branch_id', (string) $emptyBranch->id)
        ->assertSee('Belum ada pengguna aktif dengan akses ke Branch ini.');
});

it('shows only one modal when editing from the task detail and returns to the detail afterwards', function () {
    $this->actingAs($this->manager);
    $task = ($this->taskFor)($this->project, '2026-10-15', ['title' => 'Edit Dari Detail', 'assigned_date' => '2026-10-10']);

    ($this->calendar)()
        ->call('openTaskDetail', $task->id)
        ->call('openEditTaskModal', $task->id)
        ->assertSet('taskModalOpen', true)
        ->assertSet('viewingTaskId', null)
        ->assertSet('restoreTaskDetailId', $task->id)
        ->call('closeTaskModal')
        ->assertSet('taskModalOpen', false)
        ->assertSet('viewingTaskId', $task->id)
        ->call('openEditTaskModal', $task->id)
        ->set('taskTitle', 'Edit Dari Detail v2')
        ->call('saveTask')
        ->assertHasNoErrors()
        ->assertSet('taskModalOpen', false)
        ->assertSet('viewingTaskId', $task->id)
        ->assertSet('restoreTaskDetailId', null);

    expect($task->fresh()->title)->toBe('Edit Dari Detail v2');

    ($this->calendar)()
        ->call('openTaskModal', '2026-10-20')
        ->call('closeTaskModal')
        ->assertSet('viewingTaskId', null);
});

it('colours calendar items by status without purple and marks deadline conditions with an accent', function () {
    $this->actingAs($this->manager);
    ($this->taskFor)($this->project, '2026-10-20', ['title' => 'Menunggu Review', 'status' => 'submitted']);
    ($this->taskFor)($this->project, '2026-10-22', ['title' => 'Perlu Revisi', 'status' => 'revision_required']);
    ($this->taskFor)($this->project, '2026-10-10', ['title' => 'Sudah Lewat', 'status' => 'in_progress', 'assigned_date' => '2026-10-05']);

    expect(RndProjectTaskStatus::Submitted->getColor())->toBe('warning')
        ->and(RndProjectTaskStatus::RevisionRequired->getColor())->toBe('danger')
        ->and(RndProjectTaskAssignmentStatus::Submitted->getColor())->toBe('warning')
        ->and(RndProjectTaskAssignmentStatus::RevisionRequired->getColor())->toBe('danger');

    ($this->calendar)()
        ->assertDontSeeHtml('purple')
        ->assertSeeHtml('border-amber-300 bg-amber-100')
        ->assertSeeHtml('border-red-300 bg-red-100')
        ->assertSeeHtml('border-l-red-600')
        ->assertSee('Terlambat:');
});

it('shows the PIC follow-up form in the ERP request style and lets the PIC drop a chosen file', function () {
    Storage::fake('b2');
    $this->pic->givePermissionTo(['view rnd project tasks']);
    $this->actingAs($this->pic);
    $task = ($this->taskFor)($this->project, '2026-10-20', ['title' => 'Task Untuk PIC']);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $this->branch->id, 'user_id' => $this->pic->id, 'status' => 'in_progress',
    ]);

    $component = ($this->calendar)()
        ->call('openTaskDetail', $task->id)
        ->assertSee('Tindak Lanjut Saya')
        ->assertSee('Catatan Progress / Kendala')
        ->assertSee('Perkiraan Selesai')
        ->assertSee('Tambah File / Foto')
        ->set('followUpAttachments', [
            UploadedFile::fake()->image('hasil-1.jpg'),
            UploadedFile::fake()->create('laporan.pdf', 20, 'application/pdf'),
        ])
        ->assertSeeHtml('wire:click="removeFollowUpAttachment(0)"')
        ->assertSeeHtml('wire:click="removeFollowUpAttachment(1)"');

    $component
        ->call('removeFollowUpAttachment', 0)
        ->assertCount('followUpAttachments', 1)
        ->assertSeeHtml('wire:click="removeFollowUpAttachment(0)"')
        ->assertDontSeeHtml('wire:click="removeFollowUpAttachment(1)"')
        ->set('followUpNotes', 'Progress hari pertama.')
        ->call('saveFollowUp', $assignment->id, 'progress')
        ->assertHasNoErrors();

    expect($assignment->followUps()->sole()->result_attachments)->toHaveCount(1);
});

it('keeps the global calendar Add Task requiring a project choice', function () {
    $this->actingAs($this->manager);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->call('openTaskModal', '2026-10-20')
        ->assertSet('taskProjectId', '')
        ->set('taskTitle', 'Global Task')
        ->set('taskCategory', 'general')
        ->set('taskDueDate', '2026-10-22')
        ->set('taskBranchRows', [['branch_id' => (string) $this->branch->id, 'user_ids' => [(string) $this->pic->id]]])
        ->call('saveTask')
        ->assertHasErrors(['taskProjectId'])
        ->set('taskProjectId', (string) $this->otherProject->id)
        ->call('saveTask')
        ->assertHasNoErrors();

    expect(RndProjectTask::query()->where('title', 'Global Task')->sole()->rnd_project_id)->toBe($this->otherProject->id);
});

it('offers Copy Task from the global calendar detail without exposing Apply Template there', function () {
    $this->actingAs($this->manager);
    $source = ($this->taskFor)($this->project, '2026-10-15', ['title' => 'Global Source']);

    Livewire::test(ListProjects::class)
        ->call('showProjectTasks')
        ->assertDontSee('Gunakan Template')
        ->call('openTaskDetail', $source->id)
        ->assertSee('Copy Task')
        ->call('openCopyTaskModal', $source->id)
        ->set('copyBranchRows', [['branch_id' => (string) $this->branch->id, 'user_ids' => [(string) $this->pic->id]]])
        ->call('copyTask')
        ->assertHasNoErrors();

    expect(RndProjectTask::query()->where('copied_from_task_id', $source->id)->sole()->rnd_project_id)->toBe($this->project->id);
});

it('refuses tasks of another project even when the ID is sent directly', function () {
    $this->actingAs($this->manager);
    $foreignTask = ($this->taskFor)($this->otherProject, '2026-10-15');

    foreach (['openTaskDetail', 'openCopyTaskModal', 'openEditTaskModal', 'cancelTask'] as $method) {
        expect(fn () => ($this->calendar)()->call($method, $foreignTask->id))->toThrow(ModelNotFoundException::class);
    }

    expect(RndProjectTask::query()->count())->toBe(1);
});

it('authorizes direct Livewire calls instead of relying on hidden buttons', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo(['view rnd project tasks', 'view all branch rnd project tasks']);
    $this->actingAs($viewer);
    $task = ($this->taskFor)($this->project, '2026-10-15');

    ($this->calendar)()
        ->assertDontSee('Tambah Task')
        ->assertDontSee('Gunakan Template')
        ->call('openTaskModal')->assertForbidden();
    ($this->calendar)()->call('saveTask')->assertForbidden();
    ($this->calendar)()->call('openApplyTemplateModal')->assertForbidden();
    ($this->calendar)()->call('applySelectedTemplate')->assertForbidden();
    ($this->calendar)()->call('openCopyTaskModal', $task->id)->assertForbidden();

    $viewer->givePermissionTo('create rnd project tasks');
    ($this->calendar)()->call('openApplyTemplateModal')->assertForbidden();
    ($this->calendar)()->call('openCopyTaskModal', $task->id)->assertForbidden();

    expect(fn () => ($this->calendar)()->set('projectId', $this->otherProject->id))->toThrow(Exception::class)
        ->and(fn () => ($this->calendar)()->set('copyingTaskId', $task->id))->toThrow(Exception::class)
        ->and(fn () => ($this->calendar)()->set('applyIdempotencyKey', 'forged'))->toThrow(Exception::class);
});

it('rejects users without the task view permission at mount', function () {
    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider);

    ($this->calendar)()->assertForbidden();
});

it('makes archived projects read-only in the UI and on the server', function () {
    $this->actingAs($this->manager);
    $task = ($this->taskFor)($this->project, '2026-10-15', ['title' => 'Archived Project Task']);
    RndProjectTaskTemplate::factory()->has(RndProjectTaskTemplateCheckpoint::factory(), 'checkpoints')->create();
    $this->project->delete();

    ($this->calendar)()
        ->assertSee('Archived Project Task')
        ->assertSee('Project sudah diarsipkan')
        ->assertDontSee('Tambah Task')
        ->assertDontSee('Gunakan Template')
        ->call('openTaskDetail', $task->id)
        ->assertDontSee('Copy Task')
        ->assertDontSee('Edit Tugas');

    ($this->calendar)()->call('openTaskModal', '2026-10-20')->assertForbidden();
    ($this->calendar)()->call('saveTask')->assertForbidden();
    ($this->calendar)()->call('openApplyTemplateModal')->assertForbidden();
    ($this->calendar)()->call('applySelectedTemplate')->assertForbidden();
    ($this->calendar)()->call('openCopyTaskModal', $task->id)->assertForbidden();
    ($this->calendar)()->call('openEditTaskModal', $task->id)->assertForbidden();

    expect(RndProjectTask::query()->count())->toBe(1);
});

it('applies a template whose branch and PIC come from the template after setting dates and previewing', function () {
    Notification::fake();
    $this->actingAs($this->manager);
    $kitchen = Branch::factory()->create(['is_active' => true]);
    $kitchenPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $kitchenPic->syncBranchAccess([$kitchen->id], $kitchen->id);
    $kitchenPic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    $template = RndProjectTaskTemplate::factory()->create(['name' => 'Launch Checklist']);
    $trial = RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Trial Resep', 'sort_order' => 1, 'description' => 'Uji resep final.']);
    $trial->syncBranchPics([['branch_id' => $kitchen->id, 'user_ids' => [$kitchenPic->id]]]);
    $tasting = RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Tasting Internal', 'sort_order' => 2]);
    $tasting->syncBranchPics([['branch_id' => $kitchen->id, 'user_ids' => [$kitchenPic->id]]]);
    $launch = RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Launching', 'sort_order' => 3]);
    $launch->syncBranchPics([
        ['branch_id' => $this->branch->id, 'user_ids' => [$this->pic->id]],
        ['branch_id' => $kitchen->id, 'user_ids' => [$kitchenPic->id]],
    ]);

    $component = ($this->calendar)()
        ->call('openApplyTemplateModal')
        ->assertSet('applyTemplateModalOpen', true)
        ->assertSee('1. Pilih Template')
        ->assertSee('Launch Checklist')
        ->set('applyTemplateId', (string) $template->id)
        ->call('selectApplyTemplate')
        ->assertHasNoErrors()
        ->assertSet('applyTemplateStep', 2)
        ->assertSee('2. Atur Template')
        ->assertSet('applyRows.0.branch_rows', [['branch_id' => (string) $kitchen->id, 'user_ids' => [(string) $kitchenPic->id]]])
        ->assertSet('applyRows.2.branch_rows.0.user_ids', [(string) $this->pic->id])
        ->assertSet('applyRows.0.assigned_date', '')
        ->assertSee('30 Oct 2026');

    expect(RndProjectTask::query()->count())->toBe(0);
    Notification::assertNothingSent();

    $component
        ->set('applyRows.1.selected', false)
        ->set('applyRows.0.title', 'Trial Resep Final')
        ->call('continueToApplyPreview')
        ->assertHasErrors(['applyRows.0.assigned_date', 'applyRows.0.due_date', 'applyRows.2.assigned_date'])
        ->assertSet('applyTemplateStep', 2)
        ->set('applyRows.0.assigned_date', '2026-10-09')
        ->set('applyRows.0.due_date', '2026-10-12')
        ->set('applyRows.2.assigned_date', '2026-10-30')
        ->set('applyRows.2.due_date', '2026-10-30')
        ->call('continueToApplyPreview')
        ->assertHasNoErrors()
        ->assertSet('applyTemplateStep', 3)
        ->assertSee('3. Preview')
        ->assertSee('2 Task akan dibuat')
        ->assertSee('Checkpoint #1')
        ->assertSee('Trial Resep Final')
        ->assertSee('Uji resep final.')
        ->assertSee('Checkpoint #3')
        ->assertDontSee('Checkpoint #2')
        ->assertSee($kitchenPic->display_username)
        ->assertSee('aria-roledescription="carousel"', false);

    expect(collect($component->instance()->applyPreviewSlides())->pluck('number')->all())->toBe([1, 3])
        ->and($component->instance()->applyPreviewSlides()[1]['branches'])->toHaveCount(2);

    $component
        ->call('applySelectedTemplate')
        ->assertHasNoErrors()
        ->assertSet('applyTemplateModalOpen', false)
        ->assertSee('Trial Resep Final')
        ->assertSee('Launching');

    $tasks = RndProjectTask::query()->with('branches')->orderBy('due_date')->get();

    expect($tasks->pluck('title')->all())->toBe(['Trial Resep Final', 'Launching'])
        ->and($tasks->first()->branches->pluck('id')->all())->toBe([$kitchen->id])
        ->and($tasks->last()->branches->pluck('id')->sort()->values()->all())->toBe(collect([$this->branch->id, $kitchen->id])->sort()->values()->all())
        ->and(RndProjectTaskTemplateApplication::query()->sole()->rnd_project_id)->toBe($this->project->id);

    // A second submit of the same wizard session (e.g. double click) never duplicates the batch.
    $component->call('applySelectedTemplate');

    expect(RndProjectTask::query()->count())->toBe(2)
        ->and(RndProjectTaskTemplateApplication::query()->count())->toBe(1);
    Notification::assertSentToTimes($kitchenPic, ProjectTaskAssignedNotification::class, 2);
    Notification::assertSentToTimes($this->pic, ProjectTaskAssignedNotification::class, 1);
});

it('lets the user change template branch and PIC per checkpoint and rejects stale template PICs', function () {
    $this->actingAs($this->manager);
    $closedBranch = Branch::factory()->create(['is_active' => false]);
    $formerPic = User::factory()->create(['is_active' => false, 'access_all_branches' => false]);
    $formerPic->syncBranchAccess([$this->branch->id], $this->branch->id);
    $formerPic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    $template = RndProjectTaskTemplate::factory()->create();
    $checkpoint = RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Trial Dapur']);
    $checkpoint->syncBranchPics([
        ['branch_id' => $this->branch->id, 'user_ids' => [$formerPic->id]],
        ['branch_id' => $closedBranch->id, 'user_ids' => []],
    ]);

    $component = ($this->calendar)()
        ->call('openApplyTemplateModal')
        ->set('applyTemplateId', (string) $template->id)
        ->call('selectApplyTemplate')
        ->assertSet('applyRows.0.branch_rows', [['branch_id' => (string) $this->branch->id, 'user_ids' => [(string) $formerPic->id]]])
        ->assertSee('PIC tidak valid')
        ->set('applyRows.0.assigned_date', '2026-10-20')
        ->set('applyRows.0.due_date', '2026-10-22')
        ->call('continueToApplyPreview')
        ->assertHasErrors(['applyRows.0.branch_rows'])
        ->assertSet('applyTemplateStep', 2)
        ->call('addBranchRow', 'applyRows.0.branch_rows')
        ->assertCount('applyRows.0.branch_rows', 2)
        ->call('removeBranchRow', 'applyRows.0.branch_rows', 1)
        ->set('applyRows.0.branch_rows.0.user_ids', [(string) $this->pic->id])
        ->call('continueToApplyPreview')
        ->assertHasNoErrors()
        ->assertSet('applyTemplateStep', 3)
        ->call('applySelectedTemplate')
        ->assertHasNoErrors();

    expect(RndProjectTask::query()->sole()->assignments()->pluck('user_id')->all())->toBe([$this->pic->id])
        ->and($checkpoint->fresh()->load(['branches', 'picUsers'])->branchPicRows()[0]['user_ids'])->toBe([(string) $formerPic->id]);

    $component->call('addBranchRow', 'applyRows.99.branch_rows')->assertNotFound();
    ($this->calendar)()->call('addBranchRow', 'projectId')->assertNotFound();
});

it('keeps input and shows errors when checkpoint dates are invalid or pass the release date', function () {
    $this->actingAs($this->manager);
    $template = RndProjectTaskTemplate::factory()->create();
    RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Trial']);

    ($this->calendar)()
        ->call('openApplyTemplateModal')
        ->call('selectApplyTemplate')
        ->assertHasErrors(['applyTemplateId'])
        ->set('applyTemplateId', (string) $template->id)
        ->call('selectApplyTemplate')
        ->set('applyRows.0.assigned_date', '2026-10-10')
        ->set('applyRows.0.due_date', '2026-10-01')
        ->call('continueToApplyPreview')
        ->assertHasErrors(['applyRows.0.due_date'])
        ->set('applyRows.0.due_date', '2026-10-31')
        ->call('continueToApplyPreview')
        ->assertHasErrors(['applyRows.0.due_date'])
        ->assertSee('tidak boleh melewati tanggal rilis Project (30 Oct 2026)')
        ->set('applyRows.0.assigned_date', '2026-11-02')
        ->set('applyRows.0.due_date', '2026-11-03')
        ->call('continueToApplyPreview')
        ->assertHasErrors(['applyRows.0.assigned_date', 'applyRows.0.due_date'])
        ->assertSet('applyTemplateStep', 2)
        ->assertSet('applyRows.0.title', 'Trial')
        ->set('applyRows.0.selected', false)
        ->call('continueToApplyPreview')
        ->assertHasErrors(['applyRows']);
});

it('warns and requires confirmation before re-applying the same template', function () {
    $this->actingAs($this->manager);
    $template = RndProjectTaskTemplate::factory()->create();
    RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Trial']);
    RndProjectTaskTemplateApplication::factory()->create(['rnd_project_id' => $this->project->id, 'rnd_project_task_template_id' => $template->id]);

    $component = ($this->calendar)()
        ->call('openApplyTemplateModal')
        ->set('applyTemplateId', (string) $template->id)
        ->call('selectApplyTemplate')
        ->set('applyRows.0.assigned_date', '2026-10-20')
        ->set('applyRows.0.due_date', '2026-10-22')
        ->set('applyRows.0.branch_rows', [['branch_id' => (string) $this->branch->id, 'user_ids' => [(string) $this->pic->id]]])
        ->call('continueToApplyPreview')
        ->assertSet('applyTemplateStep', 3)
        ->assertSee('sudah pernah diterapkan')
        ->call('applySelectedTemplate')
        ->assertHasErrors(['applyConfirmDuplicate'])
        ->assertSet('applyTemplateModalOpen', true);

    expect(RndProjectTask::query()->count())->toBe(0);

    $component->set('applyConfirmDuplicate', true)->call('applySelectedTemplate')->assertHasNoErrors();

    expect(RndProjectTask::query()->count())->toBe(1);
});

it('does not offer inactive templates and rejects them if submitted directly', function () {
    $this->actingAs($this->manager);
    $inactive = RndProjectTaskTemplate::factory()->inactive()->create(['name' => 'Retired Template']);
    RndProjectTaskTemplateCheckpoint::factory()->for($inactive, 'template')->create();

    ($this->calendar)()
        ->call('openApplyTemplateModal')
        ->assertDontSee('Retired Template')
        ->assertSee('Belum ada template aktif')
        ->set('applyTemplateId', (string) $inactive->id)
        ->call('selectApplyTemplate')
        ->assertHasErrors(['applyTemplateId']);
});

it('copies a task from its detail with a new date, kept duration, and fresh PIC confirmation', function () {
    Notification::fake();
    $this->actingAs($this->manager);
    $source = ($this->taskFor)($this->project, '2026-10-12', [
        'title' => 'Uji Rasa Batch 1', 'assigned_date' => '2026-10-10', 'status' => 'completed',
        'instruction_attachments' => ['rnd/project-tasks/1/instructions/brief.pdf'],
    ]);
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $source->id, 'branch_id' => $this->branch->id, 'user_id' => $this->pic->id, 'status' => 'approved',
    ]);

    ($this->calendar)()
        ->call('openTaskDetail', $source->id)
        ->assertSee('Copy Task')
        ->call('openCopyTaskModal', $source->id)
        ->assertSet('copyModalOpen', true)
        ->assertSet('viewingTaskId', null)
        ->assertSet('copyTitle', 'Uji Rasa Batch 1')
        ->assertSet('copyBranchRows', [['branch_id' => (string) $this->branch->id, 'user_ids' => [(string) $this->pic->id]]])
        ->set('copyAssignedDate', '2026-11-20')
        ->assertSet('copyDueDate', '2026-11-22')
        ->set('copyTitle', 'Uji Rasa Batch 2')
        ->call('copyTask')
        ->assertHasNoErrors()
        ->assertSet('copyModalOpen', false)
        ->assertSet('calendarMonth', '2026-11')
        ->assertSee('Uji Rasa Batch 2');

    $copy = RndProjectTask::query()->where('title', 'Uji Rasa Batch 2')->sole();

    expect($copy->copied_from_task_id)->toBe($source->id)
        ->and($copy->rnd_project_id)->toBe($this->project->id)
        ->and($copy->status->value)->toBe('assigned')
        ->and($copy->instruction_attachments)->toBeNull()
        ->and($source->fresh()->status->value)->toBe('completed');
});

it('renders the calendar without growing queries per task', function () {
    $this->actingAs($this->manager);

    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        ($this->calendar)();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $firstTask = ($this->taskFor)($this->project, '2026-10-05');
    RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $firstTask->id, 'branch_id' => $this->branch->id, 'user_id' => $this->pic->id]);
    $countQueries(); // Warm the per-user permission cache so only calendar queries are compared.
    $withOneTask = $countQueries();

    foreach (range(1, 10) as $day) {
        $task = ($this->taskFor)($this->project, sprintf('2026-10-%02d', $day + 10));
        RndProjectTaskAssignment::factory()->create(['rnd_project_task_id' => $task->id, 'branch_id' => $this->branch->id, 'user_id' => $this->pic->id]);
    }

    expect($countQueries())->toBe($withOneTask);
});
