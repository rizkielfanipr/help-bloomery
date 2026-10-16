<?php

namespace App\Filament\Casual\Pages;

use App\Actions\Briefing\BriefingSubmissionAction;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use App\Services\BriefingAssignmentService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

class DailyBriefingPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.daily-briefing-page';

    public ?array $taskData = [];

    public ?string $activeTaskKey = null;

    public ?string $activeSubmissionType = null;

    public ?string $activeTaskLabel = null;

    public ?string $activeTaskNoteType = null;

    /** @var string[] */
    public array $cameraPhotoPaths = [];

    public int $taskModalKey = 0;

    /**
     * docs/daily-briefing-remediation-prd.md §10.1: authenticated, active STORE_STAFF with the
     * submit permission, a branch, and that branch an active Daily Briefing participant today.
     * A hidden link to this page is not a substitute for this check — Filament still resolves the
     * route directly.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return app(BriefingAssignmentService::class)->canSubmit($user, now());
    }

    public function getTitle(): string|Htmlable
    {
        return 'Daily Briefing';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function openTaskModal(string $taskKey): void
    {
        $user = auth()->user();
        $task = BriefingTask::cached()->firstWhere('key', $taskKey);

        if (! $task || ! app(BriefingAssignmentService::class)->taskIsAssignedTo($user, $task, now())) {
            return;
        }

        $item = $this->canonicalItem($task);

        if ($item?->review_status === BriefingReviewStatus::Approved) {
            Notification::make()
                ->title('Already Approved')
                ->body('This item has been approved and can no longer be changed.')
                ->warning()
                ->send();

            return;
        }

        $this->activeTaskKey = $taskKey;
        $this->activeSubmissionType = $task->submission_type->value;
        $this->activeTaskLabel = $task->label;
        $this->activeTaskNoteType = $task->note_type;
        $this->taskData = ['notes' => null];
        $this->cameraPhotoPaths = [];
        $this->taskModalKey++;
        $this->dispatch('open-task-modal');
    }

    public function storeCameraPhoto(string $base64Data): void
    {
        if (count($this->cameraPhotoPaths) >= 5) {
            return;
        }

        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $base64Data)) {
            return;
        }

        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
        $decoded = base64_decode($imageData, strict: true);

        if (! $decoded) {
            return;
        }

        $path = 'briefing-photos/'.now()->format('YmdHis').'_'.auth()->id().'_'.uniqid().'.jpg';
        Storage::disk('b2')->put($path, $decoded);

        $this->cameraPhotoPaths[] = $path;
    }

    public function removePhoto(int $index): void
    {
        if (! isset($this->cameraPhotoPaths[$index])) {
            return;
        }

        Storage::disk('b2')->delete($this->cameraPhotoPaths[$index]);

        array_splice($this->cameraPhotoPaths, $index, 1);
    }

    public function saveTask(): void
    {
        if (! $this->activeTaskKey) {
            return;
        }

        $task = BriefingTask::cached()->firstWhere('key', $this->activeTaskKey);

        if (! $task) {
            return;
        }

        $result = app(BriefingSubmissionAction::class)->execute(
            auth()->user(),
            $task,
            [
                'notes' => $this->taskData['notes'] ?? null,
                'photo_paths' => $this->cameraPhotoPaths,
            ],
            now(),
        );

        if (! $result->successful) {
            Notification::make()
                ->title($result->reason === 'deadline_passed' ? 'Deadline Terlewat' : 'Tidak Dapat Disimpan')
                ->body($result->message)
                ->danger()
                ->send();

            return;
        }

        $this->activeTaskKey = null;
        $this->activeSubmissionType = null;
        $this->activeTaskLabel = null;
        $this->activeTaskNoteType = null;
        $this->taskData = [];
        $this->cameraPhotoPaths = [];
        $this->dispatch('close-task-modal');

        unset($this->briefingData);

        Notification::make()
            ->title('Tugas Selesai!')
            ->body($result->message)
            ->success()
            ->send();
    }

    private function canonicalItem(BriefingTask $task): ?BriefingItem
    {
        $branchId = (int) auth()->user()->branch_id;

        return BriefingRecord::itemsForBranchPeriod($branchId, $task->period, now())->get($task->key);
    }

    #[Computed]
    public function briefingData(): array
    {
        $user = auth()->user();
        $branchId = $user?->branch_id;
        $assignments = app(BriefingAssignmentService::class);
        $result = [];

        foreach (BriefingPeriod::cases() as $period) {
            $tasks = $assignments->tasksFor($user, $period, now());
            $itemMap = $branchId
                ? BriefingRecord::itemsForBranchPeriod((int) $branchId, $period, now())
                : collect();

            $taskList = $tasks->map(function (BriefingTask $task) use ($itemMap): array {
                $item = $itemMap->get($task->key);

                return [
                    'key' => $task->key,
                    'label' => $task->label,
                    'noteType' => $task->note_type,
                    'submissionType' => $task->submission_type->value,
                    'requiresPhoto' => $task->submission_type->requiresPhoto(),
                    'group' => $task->group,
                    'groupLabel' => $task->group_label,
                    'isCompleted' => $item?->is_completed ?? false,
                    'completedAt' => $item?->completed_at,
                    'photoPaths' => $item?->photo_paths ?? [],
                    'notes' => $item?->notes,
                    'reviewStatus' => $item?->review_status,
                    'rejectionReason' => $item?->rejection_reason,
                    'hasOpenReopenGrant' => $item?->hasOpenReopenGrant() ?? false,
                    'isPastDeadline' => $task->isPastDeadline() && ! ($item?->is_completed) && ! ($item?->hasOpenReopenGrant()),
                    'deadlineLabel' => $task->deadlineLabel(),
                    'isAssigned' => true,
                ];
            });

            $historicalItems = $itemMap
                ->except($tasks->pluck('key'))
                ->filter(fn (BriefingItem $item): bool => $item->is_completed || ! empty($item->photo_paths) || filled($item->notes))
                ->map(function (BriefingItem $item): array {
                    $task = BriefingTask::cached()->firstWhere('key', $item->task_key);

                    return [
                        'key' => $item->task_key,
                        'label' => $task?->label ?? $item->task_key,
                        'noteType' => $task?->note_type ?? 'Riwayat submission',
                        'submissionType' => $task?->submission_type->value,
                        'requiresPhoto' => $task?->submission_type->requiresPhoto() ?? ! empty($item->photo_paths),
                        'group' => null,
                        'groupLabel' => null,
                        'isCompleted' => $item->is_completed,
                        'completedAt' => $item->completed_at,
                        'photoPaths' => $item->photo_paths ?? [],
                        'notes' => $item->notes,
                        'reviewStatus' => $item->review_status,
                        'rejectionReason' => $item->rejection_reason,
                        'hasOpenReopenGrant' => false,
                        'isPastDeadline' => false,
                        'deadlineLabel' => null,
                        'isAssigned' => false,
                    ];
                });

            $taskList = $taskList->concat($historicalItems)->values()->all();

            $assignedTasks = collect($taskList)->where('isAssigned', true);
            $completedCount = $assignedTasks->where('isCompleted', true)->count();

            $idDays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $idMonths = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

            $rangeLabel = match ($period) {
                BriefingPeriod::Daily => $idDays[now()->dayOfWeek].', '.now()->format('j').' '.$idMonths[now()->month].' '.now()->format('Y'),
                BriefingPeriod::Weekly => (function () use ($idMonths): string {
                    $start = now()->startOfWeek();
                    $end = now()->endOfWeek();
                    $range = $start->month === $end->month
                        ? $start->format('j').'–'.$end->format('j').' '.$idMonths[$end->month].' '.$end->format('Y')
                        : $start->format('j').' '.$idMonths[$start->month].'–'.$end->format('j').' '.$idMonths[$end->month].' '.$end->format('Y');

                    return "Periode {$range}";
                })(),
                BriefingPeriod::Monthly => $idMonths[now()->month].' '.now()->format('Y'),
            };

            $result[$period->value] = [
                'period' => $period,
                'label' => $period->getLabel(),
                'periodLabel' => $period->periodLabel(),
                'rangeLabel' => $rangeLabel,
                'total' => $assignedTasks->count(),
                'completed' => $completedCount,
                'tasks' => $taskList,
            ];
        }

        return $result;
    }
}
