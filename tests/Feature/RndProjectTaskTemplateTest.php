<?php

use App\Enums\RndProjectTaskCategory;
use App\Enums\RndProjectTaskPriority;
use App\Models\Branch;
use App\Models\RndProject;
use App\Models\RndProjectTask;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateApplication;
use App\Models\RndProjectTaskTemplateCheckpoint;
use App\Models\User;

it('relates a template to its creator, checkpoints, and applications', function () {
    $creator = User::factory()->create();
    $template = RndProjectTaskTemplate::factory()->create(['created_by' => $creator->id]);
    RndProjectTaskTemplateCheckpoint::factory()->count(2)->for($template, 'template')->create();
    $application = RndProjectTaskTemplateApplication::factory()->create(['rnd_project_task_template_id' => $template->id]);

    expect($template->creator->is($creator))->toBeTrue()
        ->and($template->checkpoints)->toHaveCount(2)
        ->and($template->applications->first()->is($application))->toBeTrue()
        ->and($template->hasBeenApplied())->toBeTrue()
        ->and($application->template->is($template))->toBeTrue();
});

it('defaults new templates to active and casts checkpoint enums', function () {
    $template = RndProjectTaskTemplate::query()->create(['name' => 'Launch Checklist']);
    $checkpoint = RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create([
        'task_type' => 'tasting', 'priority' => 'high',
    ]);

    expect($template->is_active)->toBeTrue()
        ->and($checkpoint->task_type)->toBe(RndProjectTaskCategory::Tasting)
        ->and($checkpoint->priority)->toBe(RndProjectTaskPriority::High);
});

it('orders checkpoints by sort order regardless of insertion order', function () {
    $template = RndProjectTaskTemplate::factory()->create();
    RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Launching', 'sort_order' => 3]);
    RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Trial Resep', 'sort_order' => 1]);
    RndProjectTaskTemplateCheckpoint::factory()->for($template, 'template')->create(['title' => 'Tasting', 'sort_order' => 2]);

    expect($template->checkpoints->pluck('title')->all())->toBe(['Trial Resep', 'Tasting', 'Launching']);
});

it('scopes active templates', function () {
    RndProjectTaskTemplate::factory()->create(['name' => 'Aktif']);
    RndProjectTaskTemplate::factory()->inactive()->create(['name' => 'Nonaktif']);

    expect(RndProjectTaskTemplate::query()->active()->pluck('name')->all())->toBe(['Aktif']);
});

it('records task provenance for template and copy sources', function () {
    $application = RndProjectTaskTemplateApplication::factory()->create();
    $checkpoint = RndProjectTaskTemplateCheckpoint::factory()->create();
    $source = RndProjectTask::factory()->create();

    $task = RndProjectTask::factory()->create([
        'rnd_project_task_template_application_id' => $application->id,
        'rnd_project_task_template_checkpoint_id' => $checkpoint->id,
        'copied_from_task_id' => $source->id,
    ]);

    expect($task->templateApplication->is($application))->toBeTrue()
        ->and($task->templateCheckpoint->is($checkpoint))->toBeTrue()
        ->and($task->copiedFromTask->is($source))->toBeTrue()
        ->and($application->tasks->first()->is($task))->toBeTrue();
});

it('keeps generated tasks when their template is deleted', function () {
    $application = RndProjectTaskTemplateApplication::factory()->create();
    $task = RndProjectTask::factory()->create(['rnd_project_task_template_application_id' => $application->id]);

    $application->template->delete();

    expect($task->fresh())->not->toBeNull()
        ->and($application->fresh()->rnd_project_task_template_id)->toBeNull()
        ->and($application->fresh()->template_name)->not->toBeEmpty();
});

it('detects tasks that belong to an archived project', function () {
    $task = RndProjectTask::factory()->create();

    expect($task->belongsToArchivedProject())->toBeFalse();

    RndProject::query()->findOrFail($task->rnd_project_id)->delete();

    expect($task->fresh()->belongsToArchivedProject())->toBeTrue()
        ->and($task->fresh()->load('project')->belongsToArchivedProject())->toBeTrue();
});

it('stores branch and PIC defaults per checkpoint and replaces them on sync', function () {
    $checkpoint = RndProjectTaskTemplateCheckpoint::factory()->create();
    [$kitchen, $outlet] = Branch::factory()->count(2)->create()->all();
    [$chef, $barista] = User::factory()->count(2)->create()->all();

    $checkpoint->syncBranchPics([
        ['branch_id' => $kitchen->id, 'user_ids' => [$chef->id, $barista->id]],
        ['branch_id' => $outlet->id, 'user_ids' => [$barista->id]],
        ['branch_id' => '', 'user_ids' => []],
    ]);

    expect($checkpoint->fresh()->load(['branches', 'picUsers'])->branchPicRows())->toEqualCanonicalizing([
        ['branch_id' => (string) $kitchen->id, 'user_ids' => [(string) $chef->id, (string) $barista->id]],
        ['branch_id' => (string) $outlet->id, 'user_ids' => [(string) $barista->id]],
    ]);

    $checkpoint->syncBranchPics([['branch_id' => $outlet->id, 'user_ids' => [$chef->id]]]);

    expect($checkpoint->fresh()->load(['branches', 'picUsers'])->branchPicRows())->toBe([
        ['branch_id' => (string) $outlet->id, 'user_ids' => [(string) $chef->id]],
    ]);
});
