<?php

use App\Livewire\RndProjectTaskCalendar;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskFollowUp;
use App\Models\User;
use Database\Seeders\RndProjectTaskFollowUpDemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->travelTo('2026-10-06 12:00:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    Storage::fake('b2');

    $this->project = RndProject::query()->create(['name' => 'Demo Project', 'start_date' => '2026-10-01', 'end_date' => '2026-10-30']);
    $this->branch = Branch::factory()->create();
    $this->pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false, 'name' => 'Pic Dapur']);
    $this->pic->syncBranchAccess([$this->branch->id], $this->branch->id);
    $this->pic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));
    $this->reviewer = User::factory()->create(['is_active' => true, 'name' => 'Reviewer Rnd']);
    $this->reviewer->givePermissionTo('review rnd project task follow ups');
    $this->reviewer->syncBranchAccess([$this->branch->id], $this->branch->id);

    $this->tasks = collect(['Trial Resep', 'Tasting Internal', 'Approval Menu', 'Quality Control', 'Product Photography', 'Launching'])
        ->map(function (string $title): RndProjectTask {
            $task = RndProjectTask::factory()->create([
                'rnd_project_id' => $this->project->id, 'title' => $title, 'status' => 'assigned',
                'assigned_date' => '2026-10-06', 'due_date' => '2026-10-21',
            ]);
            $task->branches()->attach($this->branch->id);
            RndProjectTaskAssignment::factory()->create([
                'rnd_project_task_id' => $task->id, 'branch_id' => $this->branch->id, 'user_id' => $this->pic->id,
                'status' => 'assigned', 'assigned_at' => now()->subHour(),
            ]);

            return $task;
        });

    $this->runSeeder = fn () => app(RndProjectTaskFollowUpDemoSeeder::class)->__invoke(['projectId' => $this->project->id]);
});

it('seeds follow-up histories with stored attachments through the real workflow', function () {
    ($this->runSeeder)();

    $assignments = RndProjectTaskAssignment::query()->with('followUps')->orderBy('rnd_project_task_id')->get();

    expect($assignments->pluck('status')->map->value->all())->toBe([
        'approved', 'in_progress', 'submitted', 'in_progress', 'in_progress', 'in_progress',
    ])
        ->and($assignments->every(fn (RndProjectTaskAssignment $assignment): bool => $assignment->followUps->isNotEmpty()))->toBeTrue()
        ->and($assignments[0]->reviewed_by)->toBe($this->reviewer->id)
        ->and($assignments[1]->review_note)->toContain('rekap skor per panelis')
        ->and($this->tasks[0]->fresh()->status->value)->toBe('completed');

    $paths = RndProjectTaskFollowUp::query()->get()->flatMap(fn (RndProjectTaskFollowUp $followUp): array => $followUp->result_attachments ?? []);

    expect($paths)->not->toBeEmpty();
    $paths->each(fn (string $path) => Storage::disk('b2')->assertExists($path));
    expect($paths->first(fn (string $path): bool => str_ends_with($path, '.pdf')))->not->toBeNull()
        ->and(Storage::disk('b2')->get($paths->first(fn (string $path): bool => str_ends_with($path, '.pdf'))))->toStartWith('%PDF-1.4');

    $followUps = RndProjectTaskFollowUp::query()->orderBy('id')->pluck('created_at');
    expect($followUps->sort()->values()->all())->toEqual($followUps->all())
        ->and($followUps->first()->gte(now()->subDay()))->toBeTrue()
        ->and($followUps->last()->lte(now()))->toBeTrue()
        ->and(RndProjectTaskAssignment::query()->get()->every(fn (RndProjectTaskAssignment $assignment): bool => $assignment->started_at->gte($assignment->assigned_at)))->toBeTrue();
});

it('skips assignments that already have follow-ups when re-run', function () {
    ($this->runSeeder)();
    $count = RndProjectTaskFollowUp::query()->count();

    ($this->runSeeder)();

    expect(RndProjectTaskFollowUp::query()->count())->toBe($count);
});

it('refuses to run outside local and testing environments', function () {
    $this->app['env'] = 'production';

    expect(fn () => ($this->runSeeder)())->toThrow(RuntimeException::class, 'local/testing');
    expect(RndProjectTaskFollowUp::query()->count())->toBe(0);
});

it('shows the complete follow-up history of every PIC to a task viewer who is not a PIC', function () {
    ($this->runSeeder)();

    $manager = User::factory()->create(['is_active' => true]);
    $manager->givePermissionTo(['view rnd project tasks', 'view all branch rnd project tasks']);
    $this->actingAs($manager);

    $approvedTask = $this->tasks[0];
    $revisionTask = $this->tasks[1];
    $attachment = RndProjectTaskFollowUp::query()
        ->whereHas('assignment', fn ($query) => $query->where('rnd_project_task_id', $approvedTask->id))
        ->whereNotNull('result_attachments')
        ->firstOrFail()
        ->result_attachments[0];

    Livewire::test(RndProjectTaskCalendar::class, ['projectId' => $this->project->id])
        ->call('openTaskDetail', $approvedTask->id)
        ->assertSee('PIC & Riwayat Tindak Lanjut')
        ->assertSee($this->pic->display_username)
        ->assertSee('Uji coba pertama selesai.')
        ->assertSee('Resep final sudah distandarkan.')
        ->assertSee('Perkiraan selesai:')
        ->assertSee('Review terakhir oleh '.$this->reviewer->display_username)
        ->assertSee('Hasil sesuai standar.')
        ->assertSee('Lampiran 2 · PDF')
        ->assertSee(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $attachment]), false)
        ->assertDontSee('Tindak Lanjut Saya')
        ->call('closeTaskDetail')
        ->call('openTaskDetail', $revisionTask->id)
        ->assertSee('Mohon tambahkan rekap skor per panelis')
        ->assertSee('Sedang melengkapi rekap per panelis');

    $this->get(route('helpdesk.rnd-project-tasks.attachments.show', ['path' => $attachment]))->assertSuccessful();
});
