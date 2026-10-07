<?php

use App\Actions\Rnd\ProjectTask\ApplyProjectTaskTemplateAction;
use App\Actions\Rnd\ProjectTask\CreateProjectTaskAction;
use App\Enums\RndProjectTaskStatus;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateApplication;
use App\Models\RndProjectTaskTemplateCheckpoint;
use App\Models\User;
use App\Notifications\ProjectTaskAssignedNotification;
use App\Services\Rnd\ProjectTask\ProjectTaskAssigneeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->project = RndProject::query()->create([
        'name' => 'Seasonal Menu', 'start_date' => '2026-10-01', 'end_date' => '2026-10-30',
    ]);
    $this->actor = User::factory()->create();
    $this->branch = Branch::factory()->create();
    $this->pic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $this->pic->syncBranchAccess([$this->branch->id], $this->branch->id);
    $this->pic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    $this->template = RndProjectTaskTemplate::factory()->create(['name' => 'Launch Checklist']);
    $this->schedule = [
        'Trial Resep' => ['trial', '2026-10-09', '2026-10-12'],
        'Tasting Internal' => ['tasting', '2026-10-16', '2026-10-18'],
        'Launching' => ['launching', '2026-10-30', '2026-10-30'],
    ];
    $this->checkpoints = collect($this->schedule)->keys()->map(fn (string $title, int $index) => RndProjectTaskTemplateCheckpoint::factory()->for($this->template, 'template')->create([
        'title' => $title, 'task_type' => $this->schedule[$title][0], 'sort_order' => $index + 1, 'description' => "Instruksi {$title}",
    ]));

    $this->payload = function (array $overrides = []): array {
        $template = $this->template->fresh('checkpoints');

        return array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'checkpoints' => $template->checkpoints->map(fn (RndProjectTaskTemplateCheckpoint $checkpoint): array => [
                'checkpoint_id' => $checkpoint->id,
                'title' => $checkpoint->title,
                'priority' => $checkpoint->priority->value,
                'assigned_date' => $this->schedule[$checkpoint->title][1],
                'due_date' => $this->schedule[$checkpoint->title][2],
                'branches' => [['branch_id' => $this->branch->id, 'user_id' => $this->pic->id]],
            ])->all(),
        ], $overrides);
    };

    $this->apply = fn (array $data, ?RndProjectTaskTemplate $template = null, ?RndProject $project = null): RndProjectTaskTemplateApplication => app(ApplyProjectTaskTemplateAction::class)
        ->execute($project ?? $this->project, ($template ?? $this->template)->fresh('checkpoints'), $data, $this->actor);
});

it('creates one Assigned task per selected checkpoint with provenance and an application record', function () {
    Notification::fake();

    $application = ($this->apply)(($this->payload)());

    $tasks = RndProjectTask::query()->orderBy('assigned_date')->get();

    expect($tasks)->toHaveCount(3)
        ->and($tasks->pluck('title')->all())->toBe(['Trial Resep', 'Tasting Internal', 'Launching'])
        ->and($tasks->pluck('assigned_date')->map->toDateString()->all())->toBe(['2026-10-09', '2026-10-16', '2026-10-30'])
        ->and($tasks->pluck('status')->unique()->all())->toBe([RndProjectTaskStatus::Assigned])
        ->and($tasks->pluck('rnd_project_id')->unique()->all())->toBe([$this->project->id])
        ->and($tasks->pluck('rnd_project_task_template_application_id')->unique()->all())->toBe([$application->id])
        ->and($tasks->pluck('rnd_project_task_template_checkpoint_id')->all())->toBe($this->checkpoints->pluck('id')->all())
        ->and($tasks->first()->description)->toBe('Instruksi Trial Resep')
        ->and($application->template_name)->toBe('Launch Checklist')
        ->and(collect($application->checkpoint_snapshot)->pluck('due_date')->all())->toBe(['2026-10-12', '2026-10-18', '2026-10-30'])
        ->and($application->applied_by)->toBe($this->actor->id)
        ->and($application->checkpoint_snapshot)->toHaveCount(3);

    Notification::assertSentToTimes($this->pic, ProjectTaskAssignedNotification::class, 3);
});

it('creates only the checkpoints kept in the preview and honours corrected values', function () {
    $payload = ($this->payload)();
    $payload['checkpoints'] = [array_merge($payload['checkpoints'][1], [
        'title' => 'Tasting Bersama Owner', 'priority' => 'urgent', 'assigned_date' => '2026-10-17', 'due_date' => '2026-10-19',
    ])];

    ($this->apply)($payload);

    $task = RndProjectTask::query()->sole();

    expect($task->title)->toBe('Tasting Bersama Owner')
        ->and($task->priority->value)->toBe('urgent')
        ->and($task->assigned_date->toDateString())->toBe('2026-10-17')
        ->and($task->task_type)->toBe('tasting');
});

it('keeps generated tasks as snapshots when the template is edited afterwards', function () {
    ($this->apply)(($this->payload)());

    $this->checkpoints->first()->update(['title' => 'Trial Resep v2', 'priority' => 'urgent']);
    $this->template->update(['name' => 'Renamed', 'is_active' => false]);

    expect(RndProjectTask::query()->pluck('title')->all())->toContain('Trial Resep')
        ->not->toContain('Trial Resep v2')
        ->and(RndProjectTaskTemplateApplication::query()->sole()->template_name)->toBe('Launch Checklist');
});

it('returns the original batch when the same idempotency key is submitted again', function () {
    $payload = ($this->payload)();

    $first = ($this->apply)($payload);
    $second = ($this->apply)($payload);

    expect($second->is($first))->toBeTrue()
        ->and(RndProjectTaskTemplateApplication::query()->count())->toBe(1)
        ->and(RndProjectTask::query()->count())->toBe(3);
});

it('requires explicit confirmation before applying the same template to the project again', function () {
    ($this->apply)(($this->payload)());

    expect(fn () => ($this->apply)(($this->payload)()))->toThrow(ValidationException::class);

    ($this->apply)(($this->payload)(['allow_duplicate' => true]));

    expect(RndProjectTask::query()->count())->toBe(6);
});

it('rolls back the whole batch and sends nothing when one checkpoint fails', function () {
    Notification::fake();

    app()->bind(CreateProjectTaskAction::class, fn ($app) => new class($app->make(ProjectTaskAssigneeResolver::class)) extends CreateProjectTaskAction
    {
        private int $calls = 0;

        public function execute(RndProject $project, array $data, User $actor, array $provenance = []): RndProjectTask
        {
            if (++$this->calls === 2) {
                throw new RuntimeException('Simulated failure on checkpoint 2');
            }

            return parent::execute($project, $data, $actor, $provenance);
        }
    });

    expect(fn () => ($this->apply)(($this->payload)()))->toThrow(RuntimeException::class);

    expect(RndProjectTask::query()->count())->toBe(0)
        ->and(RndProjectTaskTemplateApplication::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('only notifies PICs after the surrounding transaction commits', function () {
    Notification::fake();

    DB::transaction(function (): void {
        ($this->apply)(($this->payload)());

        Notification::assertNothingSent();
    });

    Notification::assertSentToTimes($this->pic, ProjectTaskAssignedNotification::class, 3);
});

it('rejects more than the maximum checkpoints per batch', function () {
    $template = RndProjectTaskTemplate::factory()->create();
    $checkpoints = RndProjectTaskTemplateCheckpoint::factory()->count(RndProjectTaskTemplate::MAX_CHECKPOINTS + 1)
        ->for($template, 'template')->sequence(fn ($sequence) => ['sort_order' => $sequence->index])->create();

    $payload = ($this->payload)(['checkpoints' => $checkpoints->map(fn ($checkpoint) => [
        'checkpoint_id' => $checkpoint->id, 'title' => $checkpoint->title, 'priority' => 'medium',
        'assigned_date' => '2026-10-01', 'due_date' => '2026-10-02',
    ])->all()]);

    expect(fn () => ($this->apply)($payload, $template))->toThrow(ValidationException::class);
    expect(RndProjectTask::query()->count())->toBe(0);
});

it('rejects an inactive template', function () {
    $this->template->update(['is_active' => false]);

    expect(fn () => ($this->apply)(($this->payload)()))->toThrow(ValidationException::class, 'Template tidak aktif');
    expect(RndProjectTask::query()->count())->toBe(0);
});

it('rejects an archived project', function () {
    $this->project->delete();

    expect(fn () => ($this->apply)(($this->payload)(), null, $this->project))->toThrow(ValidationException::class, 'diarsipkan');
    expect(RndProjectTask::query()->count())->toBe(0);
});

it('rejects a PIC outside a checkpoint branch before creating anything and pins the error to that checkpoint', function () {
    $otherBranch = Branch::factory()->create();

    $payload = ($this->payload)();
    $payload['checkpoints'][1]['branches'] = [['branch_id' => $otherBranch->id, 'user_id' => $this->pic->id]];

    try {
        ($this->apply)($payload);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['checkpoints.1.branches']);
    }

    expect(RndProjectTask::query()->count())->toBe(0)
        ->and(RndProjectTaskTemplateApplication::query()->count())->toBe(0);
});

it('shares each checkpoint with its own branches and PICs', function () {
    Notification::fake();
    $kitchen = Branch::factory()->create();
    $kitchenPic = User::factory()->create(['is_active' => true, 'access_all_branches' => false]);
    $kitchenPic->syncBranchAccess([$kitchen->id], $kitchen->id);
    $kitchenPic->givePermissionTo(Permission::findOrCreate('respond rnd project tasks', 'web'));

    $payload = ($this->payload)();
    $payload['checkpoints'][0]['branches'] = [['branch_id' => $kitchen->id, 'user_id' => $kitchenPic->id]];
    $payload['checkpoints'][2]['branches'] = [
        ['branch_id' => $this->branch->id, 'user_id' => $this->pic->id],
        ['branch_id' => $kitchen->id, 'user_id' => $kitchenPic->id],
    ];

    $application = ($this->apply)($payload);
    $tasks = RndProjectTask::query()->with(['branches', 'assignments'])->orderBy('due_date')->get();

    expect($tasks[0]->branches->pluck('id')->all())->toBe([$kitchen->id])
        ->and($tasks[1]->branches->pluck('id')->all())->toBe([$this->branch->id])
        ->and($tasks[2]->branches->pluck('id')->sort()->values()->all())->toBe(collect([$this->branch->id, $kitchen->id])->sort()->values()->all())
        ->and($tasks[2]->assignments)->toHaveCount(2)
        ->and($application->checkpoint_snapshot[0]['branches'])->toBe([['branch_id' => $kitchen->id, 'user_id' => $kitchenPic->id]])
        ->and($application->checkpoint_snapshot[0])->not->toHaveKey('index');

    Notification::assertSentToTimes($kitchenPic, ProjectTaskAssignedNotification::class, 2);
    Notification::assertSentToTimes($this->pic, ProjectTaskAssignedNotification::class, 2);
});

it('requires branches and PICs on every checkpoint', function () {
    $payload = ($this->payload)();
    $payload['checkpoints'][2]['branches'] = [];

    expect(fn () => ($this->apply)($payload))->toThrow(ValidationException::class, 'Branch');
    expect(RndProjectTask::query()->count())->toBe(0);
});

it('rejects a preview row whose deadline is before its assign date', function () {
    $payload = ($this->payload)();
    $payload['checkpoints'][2]['due_date'] = '2026-10-01';

    expect(fn () => ($this->apply)($payload))->toThrow(ValidationException::class);
    expect(RndProjectTask::query()->count())->toBe(0);
});

it('rejects a checkpoint that belongs to another template', function () {
    $foreign = RndProjectTaskTemplateCheckpoint::factory()->create();
    $payload = ($this->payload)();
    $payload['checkpoints'][0]['checkpoint_id'] = $foreign->id;

    expect(fn () => ($this->apply)($payload))->toThrow(ValidationException::class, 'Checkpoint tidak ditemukan');
    expect(RndProjectTask::query()->count())->toBe(0);
});

it('allows dates up to and including the project release date', function () {
    $payload = ($this->payload)();
    $payload['checkpoints'][2]['assigned_date'] = '2026-10-30';
    $payload['checkpoints'][2]['due_date'] = '2026-10-30';

    ($this->apply)($payload);

    expect(RndProjectTask::query()->max('due_date'))->toStartWith('2026-10-30');
});

it('rejects an assign date or deadline after the project release date', function (string $field) {
    $payload = ($this->payload)();
    $payload['checkpoints'][2]['assigned_date'] = '2026-10-29';
    $payload['checkpoints'][2][$field] = '2026-10-31';

    expect(fn () => ($this->apply)($payload))->toThrow(ValidationException::class, 'tanggal rilis Project');
    expect(RndProjectTask::query()->count())->toBe(0)
        ->and(RndProjectTaskTemplateApplication::query()->count())->toBe(0);
})->with(['assigned_date', 'due_date']);

it('requires every selected checkpoint to have dates', function () {
    $payload = ($this->payload)();
    $payload['checkpoints'][0]['assigned_date'] = '';

    expect(fn () => ($this->apply)($payload))->toThrow(ValidationException::class);
    expect(RndProjectTask::query()->count())->toBe(0);
});
