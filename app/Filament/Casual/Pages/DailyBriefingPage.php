<?php

namespace App\Filament\Casual\Pages;

use App\Actions\Briefing\BriefingSubmissionAction;
use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
use App\Services\BriefingAssignmentService;
use App\Services\BriefingBackdateEligibility;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

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

    #[Url(as: 'backdate')]
    public bool $requestBackdate = false;

    #[Url(as: 'date')]
    public string $briefingDate = '';

    public string $requestedBackdate = '';

    public function mount(): void
    {
        $today = now(config('app.timezone'))->startOfDay();
        $requestedDate = $this->parseDate($this->briefingDate);

        if ($this->requestBackdate) {
            $eligibility = app(BriefingBackdateEligibility::class);
            $selectedDate = $requestedDate && $eligibility->isWithinWindow($requestedDate)
                ? $requestedDate
                : $eligibility->latestDate();

            $this->briefingDate = $selectedDate->toDateString();
        } else {
            $this->briefingDate = $today->toDateString();
        }

        $this->requestedBackdate = $this->briefingDate;
    }

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

    public function selectedDate(): Carbon
    {
        return $this->parseDate($this->briefingDate)
            ?? now(config('app.timezone'))->startOfDay();
    }

    public function isBackdateMode(): bool
    {
        return $this->requestBackdate
            && $this->selectedDate()->isBefore(now(config('app.timezone'))->startOfDay());
    }

    public function backdateEarliestDate(): string
    {
        return app(BriefingBackdateEligibility::class)->earliestDate()->toDateString();
    }

    public function backdateLatestDate(): string
    {
        return app(BriefingBackdateEligibility::class)->latestDate()->toDateString();
    }

    public function applyBackdate(): void
    {
        $this->resetErrorBag('requestedBackdate');
        $date = $this->parseDate($this->requestedBackdate);

        if (! $date) {
            $this->addError('requestedBackdate', 'Pilih tanggal backdate yang valid.');

            return;
        }

        $reason = app(BriefingBackdateEligibility::class)->denialReason(auth()->user(), $date);

        if ($reason) {
            $this->addError('requestedBackdate', $reason);

            return;
        }

        $this->requestBackdate = true;
        $this->briefingDate = $date->toDateString();
        $this->resetTaskModal();
        unset($this->briefingData);

        Notification::make()
            ->title('Tanggal Backdate Dipilih')
            ->body('Menampilkan poin yang belum diinput pada '.$date->locale('id')->isoFormat('D MMMM Y').'.')
            ->success()
            ->send();
    }

    public function openTaskModal(string $taskKey): void
    {
        $user = auth()->user();
        $task = BriefingTask::cached()->firstWhere('key', $taskKey);
        $recordDate = $this->selectedDate();

        if (! $task || ! app(BriefingAssignmentService::class)->taskIsAssignedTo($user, $task, $recordDate)) {
            return;
        }

        $item = $this->canonicalItem($task);

        if ($this->isBackdateMode() && $this->itemHasSubmission($item)) {
            Notification::make()
                ->title('Poin Sudah Diinput')
                ->body('Poin ini sudah memiliki input pada periode yang dipilih.')
                ->warning()
                ->send();

            return;
        }

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
            $this->selectedDate(),
        );

        if (! $result->successful) {
            Notification::make()
                ->title($result->reason === 'deadline_passed' ? 'Deadline Terlewat' : 'Tidak Dapat Disimpan')
                ->body($result->message)
                ->danger()
                ->send();

            return;
        }

        $this->resetTaskModal();
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

        return BriefingRecord::itemsForBranchPeriod($branchId, $task->period, $this->selectedDate())->get($task->key);
    }

    #[Computed]
    public function briefingData(): array
    {
        $user = auth()->user();
        $branchId = $user?->branch_id;
        $assignments = app(BriefingAssignmentService::class);
        $recordDate = $this->selectedDate();
        $isBackdateMode = $this->isBackdateMode();
        $result = [];

        foreach (BriefingPeriod::cases() as $period) {
            $tasks = $assignments->tasksFor($user, $period, $recordDate);
            $itemMap = $branchId
                ? BriefingRecord::itemsForBranchPeriod((int) $branchId, $period, $recordDate)
                : collect();

            $taskList = $tasks->map(function (BriefingTask $task) use ($itemMap, $isBackdateMode, $recordDate): array {
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
                    'hasSubmission' => $this->itemHasSubmission($item),
                    'isPastDeadline' => ! $isBackdateMode && $task->isPastDeadline($recordDate) && ! ($item?->is_completed) && ! ($item?->hasOpenReopenGrant()),
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
                        'hasSubmission' => $this->itemHasSubmission($item),
                        'isPastDeadline' => false,
                        'deadlineLabel' => null,
                        'isAssigned' => false,
                    ];
                });

            $allTasks = $taskList->concat($historicalItems)->values();
            $assignedTasks = $allTasks->where('isAssigned', true);
            $completedCount = $assignedTasks->where('isCompleted', true)->count();
            $submittedCount = $assignedTasks->where('hasSubmission', true)->count();
            $visibleTasks = $isBackdateMode
                ? $allTasks->filter(fn (array $task): bool => $task['isAssigned'] && ! $task['hasSubmission'])->values()->all()
                : $allTasks->all();

            $idDays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $idMonths = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

            $rangeLabel = match ($period) {
                BriefingPeriod::Daily => $idDays[$recordDate->dayOfWeek].', '.$recordDate->format('j').' '.$idMonths[$recordDate->month].' '.$recordDate->format('Y'),
                BriefingPeriod::Weekly => (function () use ($idMonths, $recordDate): string {
                    $start = $recordDate->copy()->startOfWeek();
                    $end = $recordDate->copy()->endOfWeek();
                    $range = $start->month === $end->month
                        ? $start->format('j').'–'.$end->format('j').' '.$idMonths[$end->month].' '.$end->format('Y')
                        : $start->format('j').' '.$idMonths[$start->month].'–'.$end->format('j').' '.$idMonths[$end->month].' '.$end->format('Y');

                    return "Periode {$range}";
                })(),
                BriefingPeriod::Monthly => $idMonths[$recordDate->month].' '.$recordDate->format('Y'),
            };

            $result[$period->value] = [
                'period' => $period,
                'label' => $period->getLabel(),
                'periodLabel' => $period->periodLabel(),
                'rangeLabel' => $rangeLabel,
                'total' => $assignedTasks->count(),
                'completed' => $completedCount,
                'submitted' => $submittedCount,
                'remaining' => max(0, $assignedTasks->count() - $submittedCount),
                'allSubmitted' => $assignedTasks->isNotEmpty() && $submittedCount === $assignedTasks->count(),
                'tasks' => $visibleTasks,
            ];
        }

        return $result;
    }

    private function itemHasSubmission(?BriefingItem $item): bool
    {
        if (! $item) {
            return false;
        }

        return in_array($item->review_status, [
            BriefingReviewStatus::LegacyPending,
            BriefingReviewStatus::SupervisorReview,
            BriefingReviewStatus::Approved,
        ], true)
            || $item->is_completed
            || $item->completed_at !== null
            || ! empty($item->photo_paths)
            || filled($item->notes);
    }

    private function resetTaskModal(): void
    {
        $this->activeTaskKey = null;
        $this->activeSubmissionType = null;
        $this->activeTaskLabel = null;
        $this->activeTaskNoteType = null;
        $this->taskData = [];
        $this->cameraPhotoPaths = [];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'))->startOfDay();

            return $date->format('Y-m-d') === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
