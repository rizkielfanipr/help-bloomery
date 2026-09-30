<?php

use App\Filament\Helpdesk\Pages\Dashboard;
use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);

    $this->project = RndProject::query()->create([
        'name' => 'Dashboard Test Project', 'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
    ]);
});

it('hides the action-needed section when the user has no active assignments', function () {
    Livewire::test(Dashboard::class)
        ->assertDontSee('Tugas yang Perlu Ditindaklanjuti');
});

it('lists only the current user\'s own active assignments on the dashboard', function () {
    $branch = Branch::factory()->create();
    $myTask = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'My Dashboard Task']);
    $myTask->branches()->attach($branch->id);
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $myTask->id, 'branch_id' => $branch->id, 'user_id' => $this->admin->id, 'status' => 'assigned',
    ]);

    $otherUser = User::factory()->create();
    $otherTask = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Someone Else Task']);
    $otherTask->branches()->attach($branch->id);
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $otherTask->id, 'branch_id' => $branch->id, 'user_id' => $otherUser->id, 'status' => 'assigned',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('Tugas yang Perlu Ditindaklanjuti')
        ->assertSee('My Dashboard Task')
        ->assertDontSee('Someone Else Task');
});

it('excludes approved and cancelled assignments from the action-needed list', function () {
    $branch = Branch::factory()->create();
    $doneTask = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Approved Task']);
    $doneTask->branches()->attach($branch->id);
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $doneTask->id, 'branch_id' => $branch->id, 'user_id' => $this->admin->id, 'status' => 'approved',
    ]);

    Livewire::test(Dashboard::class)->assertDontSee('Approved Task');
});

it('opens the task detail directly when linked with ?openTask=', function () {
    $branch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Deep Linked Task']);
    $task->branches()->attach($branch->id);
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $this->admin->id, 'status' => 'assigned',
    ]);

    $this->get(ProjectResource::getUrl('index', ['openTask' => $task->id]))
        ->assertOk()
        ->assertSee('Deep Linked Task');
});

it('shows the reminder modal once per session while active assignments exist', function () {
    $branch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'title' => 'Modal Task']);
    $task->branches()->attach($branch->id);
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $this->admin->id, 'status' => 'assigned',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSet('showTaskReminderModal', true)
        ->assertSee('Modal Task');

    Livewire::test(Dashboard::class)
        ->assertSet('showTaskReminderModal', false);
});

it('does not complete the task when the reminder modal is dismissed', function () {
    $branch = Branch::factory()->create();
    $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id, 'status' => 'assigned']);
    $task->branches()->attach($branch->id);
    RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'user_id' => $this->admin->id, 'status' => 'assigned',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSet('showTaskReminderModal', true)
        ->call('dismissTaskReminderModal')
        ->assertSet('showTaskReminderModal', false);

    expect($task->fresh()->status->value)->toBe('assigned');
});

it('hides the reminder modal entirely when there are no active assignments', function () {
    Livewire::test(Dashboard::class)->assertSet('showTaskReminderModal', false);
});
