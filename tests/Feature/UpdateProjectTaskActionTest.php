<?php

use App\Actions\Rnd\ProjectTask\UpdateProjectTaskAction;
use App\Models\RndProjectTask;
use Illuminate\Validation\ValidationException;

it('updates a task\'s own fields', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned', 'title' => 'Old Title']);

    $updated = app(UpdateProjectTaskAction::class)->execute($task, [
        'title' => 'New Title',
        'task_type' => 'production',
        'description' => 'Catatan baru.',
        'assigned_date' => '2026-10-01',
        'due_date' => '2026-10-10',
        'priority' => 'urgent',
        'instruction_attachments' => null,
    ]);

    expect($updated->title)->toBe('New Title')
        ->and($updated->task_type)->toBe('production')
        ->and($updated->priority->value)->toBe('urgent');
});

it('rejects a deadline earlier than the assign date on update', function () {
    $task = RndProjectTask::factory()->create(['status' => 'assigned']);

    expect(fn () => app(UpdateProjectTaskAction::class)->execute($task, [
        'title' => 'X', 'task_type' => 'general', 'description' => null,
        'assigned_date' => '2026-10-10', 'due_date' => '2026-10-01', 'priority' => 'medium',
        'instruction_attachments' => null,
    ]))->toThrow(ValidationException::class);
});

it('refuses to update a task that already reached a terminal status', function () {
    $task = RndProjectTask::factory()->create(['status' => 'cancelled']);

    expect(fn () => app(UpdateProjectTaskAction::class)->execute($task, [
        'title' => 'X', 'task_type' => 'general', 'description' => null,
        'assigned_date' => '2026-10-01', 'due_date' => '2026-10-05', 'priority' => 'medium',
        'instruction_attachments' => null,
    ]))->toThrow(RuntimeException::class);
});
