<?php

namespace App\Actions\Rnd\ProjectTask;

use App\Enums\RndProjectTaskPriority;
use App\Models\RndProject;
use App\Models\RndProjectTaskTemplate;
use App\Models\RndProjectTaskTemplateApplication;
use App\Models\RndProjectTaskTemplateCheckpoint;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Gunakan Template" (docs/rnd-project-checkpoint-calendar-prd.md §10.2, §17.1, §18): turns the
 * selected checkpoints of an active template into independent Task snapshots on one Project.
 *
 * - The user sets each checkpoint's assign date, deadline, and Branch/PIC pairs while applying
 *   (Branches may be pre-filled from the template); dates never fall after the Project release
 *   date (`rnd_projects.end_date`).
 * - Every row, date, and Branch/PIC pair is validated before the first insert.
 * - The application record and every Task, Branch pivot, and assignment are written in one
 *   transaction — one failing checkpoint rolls back the whole batch.
 * - `idempotency_key` is unique: replaying a key returns the original batch instead of creating
 *   a second one.
 * - PIC notifications are deferred until the transaction commits (see `CreateProjectTaskAction`).
 *
 * Authorization is the caller's responsibility (`RndProjectTaskPolicy::applyTemplate`).
 *
 * @phpstan-type ApplyRow array{checkpoint_id: int, title: string, priority: string, assigned_date: string, due_date: string, branches: list<array{branch_id: int, user_id: int}>}
 * @phpstan-type ApplyData array{idempotency_key: string, allow_duplicate?: bool, checkpoints: list<ApplyRow>}
 */
class ApplyProjectTaskTemplateAction
{
    public function __construct(private readonly CreateProjectTaskAction $createProjectTask) {}

    /**
     * @param  ApplyData  $data
     */
    public function execute(RndProject $project, RndProjectTaskTemplate $template, array $data, User $actor): RndProjectTaskTemplateApplication
    {
        $existingApplication = $this->findExistingApplication($project, $data['idempotency_key'] ?? '');

        if ($existingApplication !== null) {
            return $existingApplication;
        }

        $this->validatePayload($project, $data);
        $this->assertApplicable($project, $template, $data);

        $rows = $this->buildRows($template, $data['checkpoints']);
        $this->assertValidRowAssignments($rows);

        try {
            return DB::transaction(function () use ($project, $template, $data, $actor, $rows): RndProjectTaskTemplateApplication {
                $application = $project->taskTemplateApplications()->create([
                    'rnd_project_task_template_id' => $template->id,
                    'template_name' => $template->name,
                    'idempotency_key' => $data['idempotency_key'],
                    'checkpoint_snapshot' => array_map(fn (array $row): array => Arr::except($row, 'index'), $rows),
                    'applied_by' => $actor->id,
                    'applied_at' => now(),
                ]);

                foreach ($rows as $row) {
                    $this->createProjectTask->execute($project, [
                        'title' => $row['title'],
                        'task_type' => $row['task_type'],
                        'description' => $row['description'],
                        'assigned_date' => $row['assigned_date'],
                        'due_date' => $row['due_date'],
                        'priority' => $row['priority'],
                        'instruction_attachments' => null,
                        'branches' => $row['branches'],
                    ], $actor, [
                        'rnd_project_task_template_application_id' => $application->id,
                        'rnd_project_task_template_checkpoint_id' => $row['checkpoint_id'],
                    ]);
                }

                return $application->load('tasks');
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->findExistingApplication($project, $data['idempotency_key']) ?? throw $exception;
        }
    }

    /**
     * Rules for one checkpoint row, shared with the Livewire wizard so both enforce the same
     * limits: deadline not before the assign date, and neither after the Project release date.
     *
     * @return array<string, list<mixed>>
     */
    public function checkpointRowRules(RndProject $project, string $rowPath): array
    {
        $releaseDate = $project->end_date->toDateString();

        return [
            "{$rowPath}.title" => ['required', 'string', 'max:255'],
            "{$rowPath}.priority" => ['required', Rule::enum(RndProjectTaskPriority::class)],
            "{$rowPath}.assigned_date" => ['required', 'date', "before_or_equal:{$releaseDate}"],
            "{$rowPath}.due_date" => ['required', 'date', "after_or_equal:{$rowPath}.assigned_date", "before_or_equal:{$releaseDate}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function checkpointRowMessages(RndProject $project, string $rowsPath): array
    {
        $releaseMessage = 'Tanggal tidak boleh melewati tanggal rilis Project ('.$project->end_date->format('d M Y').').';

        return [
            "{$rowsPath}.*.title.required" => 'Nama Task wajib diisi.',
            "{$rowsPath}.*.assigned_date.required" => 'Tanggal assign wajib diisi.',
            "{$rowsPath}.*.due_date.required" => 'Deadline wajib diisi.',
            "{$rowsPath}.*.due_date.after_or_equal" => 'Deadline tidak boleh lebih awal dari tanggal assign.',
            "{$rowsPath}.*.assigned_date.before_or_equal" => $releaseMessage,
            "{$rowsPath}.*.due_date.before_or_equal" => $releaseMessage,
        ];
    }

    /**
     * Was this template already applied to the Project? Drives the duplicate warning (§12.11).
     */
    public function hasBeenAppliedTo(RndProject $project, RndProjectTaskTemplate $template): bool
    {
        return $project->taskTemplateApplications()
            ->where('rnd_project_task_template_id', $template->id)
            ->exists();
    }

    private function findExistingApplication(RndProject $project, string $idempotencyKey): ?RndProjectTaskTemplateApplication
    {
        if ($idempotencyKey === '') {
            return null;
        }

        $application = RndProjectTaskTemplateApplication::query()
            ->with('tasks')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($application !== null && $application->rnd_project_id !== $project->id) {
            throw ValidationException::withMessages(['idempotency_key' => 'Sesi penerapan template tidak valid. Muat ulang halaman.']);
        }

        return $application;
    }

    /**
     * @param  ApplyData  $data
     */
    private function validatePayload(RndProject $project, array $data): void
    {
        Validator::make($data, [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'checkpoints' => ['required', 'array', 'min:1', 'max:'.RndProjectTaskTemplate::MAX_CHECKPOINTS],
            'checkpoints.*.checkpoint_id' => ['required', 'integer', 'distinct'],
            ...$this->checkpointRowRules($project, 'checkpoints.*'),
            'checkpoints.*.branches' => ['required', 'array', 'min:1'],
            'checkpoints.*.branches.*.branch_id' => ['required', 'integer'],
            'checkpoints.*.branches.*.user_id' => ['required', 'integer'],
        ], [
            'checkpoints.required' => 'Pilih minimal satu checkpoint.',
            'checkpoints.min' => 'Pilih minimal satu checkpoint.',
            'checkpoints.max' => 'Maksimal :max checkpoint dapat diterapkan dalam satu kali proses.',
            ...$this->checkpointRowMessages($project, 'checkpoints'),
            'checkpoints.*.branches.required' => 'Pilih minimal satu Branch beserta PIC-nya.',
        ])->validate();
    }

    /**
     * @param  ApplyData  $data
     */
    private function assertApplicable(RndProject $project, RndProjectTaskTemplate $template, array $data): void
    {
        if ($project->trashed()) {
            throw ValidationException::withMessages(['project' => 'Project sudah diarsipkan sehingga tidak dapat menerima Task baru.']);
        }

        if (! $template->is_active) {
            throw ValidationException::withMessages(['template' => 'Template tidak aktif sehingga tidak dapat diterapkan.']);
        }

        if (! ($data['allow_duplicate'] ?? false) && $this->hasBeenAppliedTo($project, $template)) {
            throw ValidationException::withMessages([
                'allow_duplicate' => 'Template ini sudah pernah diterapkan pada Project ini. Konfirmasi untuk menerapkannya lagi.',
            ]);
        }
    }

    /**
     * Joins each submitted row with its checkpoint so category and description always come from
     * the template itself, never from the browser.
     *
     * @param  list<ApplyRow>  $submittedRows
     * @return list<array{index: int, checkpoint_id: int, sort_order: int, title: string, task_type: string, description: ?string, priority: string, assigned_date: string, due_date: string, branches: list<array{branch_id: int, user_id: int}>}>
     */
    private function buildRows(RndProjectTaskTemplate $template, array $submittedRows): array
    {
        $checkpoints = $template->checkpoints->keyBy('id');

        return collect($submittedRows)
            ->map(function (array $row, int $index) use ($checkpoints): array {
                /** @var RndProjectTaskTemplateCheckpoint|null $checkpoint */
                $checkpoint = $checkpoints->get((int) $row['checkpoint_id']);

                if ($checkpoint === null) {
                    throw ValidationException::withMessages([
                        "checkpoints.{$index}.checkpoint_id" => 'Checkpoint tidak ditemukan pada template ini.',
                    ]);
                }

                return [
                    'index' => $index,
                    'checkpoint_id' => $checkpoint->id,
                    'sort_order' => $checkpoint->sort_order,
                    'title' => trim($row['title']),
                    'task_type' => $checkpoint->task_type->value,
                    'description' => $checkpoint->description,
                    'priority' => $row['priority'],
                    'assigned_date' => $row['assigned_date'],
                    'due_date' => $row['due_date'],
                    'branches' => collect($row['branches'])
                        ->map(fn (array $pair): array => ['branch_id' => (int) $pair['branch_id'], 'user_id' => (int) $pair['user_id']])
                        ->all(),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    /**
     * Re-checks every checkpoint's Branch/PIC pairs before the first insert so one stale PIC
     * rejects the batch up front, with the error pinned to that checkpoint.
     *
     * @param  list<array{index: int, branches: list<array{branch_id: int, user_id: int}>}>  $rows
     */
    private function assertValidRowAssignments(array $rows): void
    {
        foreach ($rows as $row) {
            try {
                $this->createProjectTask->assertValidAssignments($row['branches']);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    "checkpoints.{$row['index']}.branches" => collect($exception->errors())->flatten()->first(),
                ]);
            }
        }
    }
}
