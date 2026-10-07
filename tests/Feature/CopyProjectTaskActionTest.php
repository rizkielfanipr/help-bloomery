<?php

use App\Actions\Rnd\ProjectTask\CopyProjectTaskAction;
use App\Enums\RndProjectTaskAssignmentStatus;
use App\Enums\RndProjectTaskStatus;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskAssignment;
use App\Models\RndProjectTaskFollowUp;
use App\Models\RndProjectTaskTemplateApplication;
use App\Models\User;
use App\Notifications\ProjectTaskAssignedNotification;
use Illuminate\Support\Facades\Notification;
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
    $this->oldPic = User::factory()->create();
    $this->reviewer = User::factory()->create();
    $application = RndProjectTaskTemplateApplication::factory()->create(['rnd_project_id' => $this->project->id]);

    $this->source = RndProjectTask::factory()->create([
        'rnd_project_id' => $this->project->id,
        'title' => 'Uji Rasa Batch 1',
        'task_type' => 'tasting',
        'description' => 'Bandingkan dengan resep lama.',
        'priority' => 'high',
        'status' => RndProjectTaskStatus::Completed->value,
        'assigned_date' => '2026-10-01',
        'due_date' => '2026-10-04',
        'instruction_attachments' => ['rnd/project-tasks/1/instructions/brief.pdf'],
        'completed_by' => $this->oldPic->id,
        'completed_at' => now(),
        'rnd_project_task_template_application_id' => $application->id,
    ]);
    $this->source->branches()->attach($this->branch->id);
    $assignment = RndProjectTaskAssignment::factory()->create([
        'rnd_project_task_id' => $this->source->id, 'branch_id' => $this->branch->id, 'user_id' => $this->oldPic->id,
        'status' => RndProjectTaskAssignmentStatus::Approved->value, 'reviewed_by' => $this->reviewer->id, 'review_note' => 'Mantap',
    ]);
    RndProjectTaskFollowUp::factory()->create(['rnd_project_task_assignment_id' => $assignment->id]);

    $this->copy = fn (array $overrides = []): RndProjectTask => app(CopyProjectTaskAction::class)->execute($this->source, array_merge([
        'title' => 'Uji Rasa Batch 2',
        'assigned_date' => '2026-11-10',
        'due_date' => '2026-11-13',
        'branches' => [['branch_id' => $this->branch->id, 'user_id' => $this->pic->id]],
    ], $overrides), $this->actor);
});

it('copies only the descriptive allowlist and records the source task', function () {
    $copy = ($this->copy)();

    expect($copy->id)->not->toBe($this->source->id)
        ->and($copy->rnd_project_id)->toBe($this->project->id)
        ->and($copy->title)->toBe('Uji Rasa Batch 2')
        ->and($copy->task_type)->toBe('tasting')
        ->and($copy->description)->toBe('Bandingkan dengan resep lama.')
        ->and($copy->priority->value)->toBe('high')
        ->and($copy->assigned_date->toDateString())->toBe('2026-11-10')
        ->and($copy->due_date->toDateString())->toBe('2026-11-13')
        ->and($copy->copied_from_task_id)->toBe($this->source->id)
        ->and($copy->copiedFromTask->is($this->source))->toBeTrue()
        ->and($copy->created_by)->toBe($this->actor->id);
});

it('does not carry over status, completion, attachments, history, review, follow-ups, or template provenance', function () {
    $copy = ($this->copy)()->fresh(['assignments.followUps']);

    expect($copy->status)->toBe(RndProjectTaskStatus::Assigned)
        ->and($copy->completed_by)->toBeNull()
        ->and($copy->completed_at)->toBeNull()
        ->and($copy->instruction_attachments)->toBeNull()
        ->and($copy->rnd_project_task_template_application_id)->toBeNull()
        ->and($copy->rnd_project_task_template_checkpoint_id)->toBeNull()
        ->and($copy->assignments)->toHaveCount(1)
        ->and($copy->assignments->first()->user_id)->toBe($this->pic->id)
        ->and($copy->assignments->first()->status)->toBe(RndProjectTaskAssignmentStatus::Assigned)
        ->and($copy->assignments->first()->reviewed_by)->toBeNull()
        ->and($copy->assignments->first()->review_note)->toBeNull()
        ->and($copy->assignments->first()->followUps)->toBeEmpty();
});

it('leaves the source task untouched', function () {
    $before = $this->source->fresh()->only(['title', 'status', 'due_date', 'completed_by']);

    ($this->copy)();

    expect($this->source->fresh()->only(['title', 'status', 'due_date', 'completed_by']))->toEqual($before)
        ->and($this->source->assignments()->count())->toBe(1);
});

it('notifies the newly confirmed PIC only', function () {
    Notification::fake();

    ($this->copy)();

    Notification::assertSentTo($this->pic, ProjectTaskAssignedNotification::class);
    Notification::assertNotSentTo($this->oldPic, ProjectTaskAssignedNotification::class);
});

it('re-validates branch and PIC access at copy time', function () {
    $otherBranch = Branch::factory()->create();

    expect(fn () => ($this->copy)(['branches' => [['branch_id' => $otherBranch->id, 'user_id' => $this->pic->id]]]))
        ->toThrow(ValidationException::class);
    expect(RndProjectTask::query()->count())->toBe(1);
});

it('rejects copying inside an archived project', function () {
    $this->project->delete();

    expect(fn () => ($this->copy)())->toThrow(ValidationException::class, 'diarsipkan');
    expect(RndProjectTask::query()->count())->toBe(1);
});

it('rejects a deadline earlier than the new assign date', function () {
    expect(fn () => ($this->copy)(['due_date' => '2026-11-01']))->toThrow(ValidationException::class);
});

it('defaults the copy deadline to the source duration from the new assign date', function () {
    $action = app(CopyProjectTaskAction::class);

    expect($action->defaultDueDate($this->source, '2026-11-10'))->toBe('2026-11-13');

    $this->source->update(['assigned_date' => '2026-10-01', 'due_date' => '2026-10-01']);

    expect($action->defaultDueDate($this->source->fresh(), '2026-11-10'))->toBe('2026-11-10');
});
