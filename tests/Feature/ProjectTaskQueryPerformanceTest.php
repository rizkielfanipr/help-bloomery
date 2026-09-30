<?php

use App\Filament\Helpdesk\Resources\Projects\Pages\ListProjects;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $this->project = RndProject::query()->create([
        'name' => 'Perf Test Project', 'start_date' => '2026-09-01', 'end_date' => '2026-12-01',
    ]);

    $this->seedTaskWithAssignments = function (int $assignmentCount): RndProjectTask {
        $task = RndProjectTask::factory()->create(['rnd_project_id' => $this->project->id]);

        foreach (range(1, $assignmentCount) as $i) {
            $branch = Branch::factory()->create();
            $task->branches()->attach($branch->id);
            $assignment = RndProjectTaskAssignment::factory()->create([
                'rnd_project_task_id' => $task->id, 'branch_id' => $branch->id, 'status' => 'submitted',
            ]);
            $assignment->followUps()->create(['follow_up_type' => 'submission', 'notes' => "Hasil {$i}."]);
        }

        return $task;
    };

    $this->queryCountForOpeningDetail = function (RndProjectTask $task): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListProjects::class)
            ->call('showProjectTasks')
            ->call('openTaskDetail', $task->id);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
});

it('does not scale query count with the number of assignments/follow-ups on a task', function () {
    $smallTask = ($this->seedTaskWithAssignments)(2);
    $largeTask = ($this->seedTaskWithAssignments)(8);

    $smallCount = ($this->queryCountForOpeningDetail)($smallTask);
    $largeCount = ($this->queryCountForOpeningDetail)($largeTask);

    // 6 extra assignments (each with a follow-up) should not add 6+ extra queries if eager
    // loading is correct — an N+1 would grow roughly linearly with the assignment count.
    expect($largeCount - $smallCount)->toBeLessThanOrEqual(2);
});
