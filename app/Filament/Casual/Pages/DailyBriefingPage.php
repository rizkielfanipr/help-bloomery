<?php

namespace App\Filament\Casual\Pages;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingSubmissionType;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingTask;
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
        $task = BriefingTask::cached()->firstWhere('key', $taskKey);

        if (! $task) {
            return;
        }

        $period = $task->period;
        $record = BriefingRecord::where('user_id', auth()->id())
            ->where('period', $period->value)
            ->whereDate('record_date', $period->recordDate())
            ->first();

        if ($record) {
            $item = BriefingItem::where('briefing_record_id', $record->id)
                ->where('task_key', $taskKey)
                ->first();

            if ($item?->review_status === BriefingReviewStatus::Approved) {
                Notification::make()
                    ->title('Sudah Disetujui')
                    ->body('Item ini sudah disetujui HR dan tidak dapat diubah.')
                    ->warning()
                    ->send();

                return;
            }
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
        Storage::disk('public')->put($path, $decoded);

        $this->cameraPhotoPaths[] = $path;
    }

    public function removePhoto(int $index): void
    {
        if (! isset($this->cameraPhotoPaths[$index])) {
            return;
        }

        Storage::disk('public')->delete($this->cameraPhotoPaths[$index]);

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

        $submissionType = $task->submission_type;
        $period = $task->period;

        if ($task->isPastDeadline()) {
            Notification::make()
                ->title('Deadline Terlewat')
                ->body('Waktu pengisian sudah berakhir. Tugas ini tidak dapat disubmit.')
                ->danger()
                ->send();

            return;
        }

        if ($submissionType->requiresPhoto() && empty($this->cameraPhotoPaths)) {
            Notification::make()
                ->title('Foto Diperlukan')
                ->body('Harap ambil foto terlebih dahulu sebagai bukti.')
                ->danger()
                ->send();

            return;
        }

        if ($submissionType->requiresText() && empty(trim($this->taskData['notes'] ?? ''))) {
            Notification::make()
                ->title('Catatan Diperlukan')
                ->body('Harap isi catatan terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        $record = BriefingRecord::where('user_id', auth()->id())
            ->where('period', $period->value)
            ->whereDate('record_date', $period->recordDate())
            ->first()
            ?? BriefingRecord::create([
                'user_id' => auth()->id(),
                'period' => $period->value,
                'record_date' => $period->recordDate(),
            ]);

        $item = BriefingItem::firstOrNew([
            'briefing_record_id' => $record->id,
            'task_key' => $this->activeTaskKey,
        ]);

        if ($item->exists && $item->review_status === BriefingReviewStatus::Approved) {
            Notification::make()
                ->title('Sudah Disetujui')
                ->body('Item ini sudah disetujui HR dan tidak dapat diubah.')
                ->warning()
                ->send();

            return;
        }

        $isAutoComplete = $submissionType !== BriefingSubmissionType::Checkbox;

        $item->fill([
            'photo_paths' => ! empty($this->cameraPhotoPaths) ? $this->cameraPhotoPaths : ($item->photo_paths ?? []),
            'notes' => $this->taskData['notes'] ?? null,
            'is_completed' => $isAutoComplete,
            'completed_at' => $isAutoComplete ? now() : null,
            'review_status' => BriefingReviewStatus::Pending->value,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        if (! $record->submitted_at) {
            $record->update(['submitted_at' => now()]);
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
            ->body('Data akan diverifikasi oleh HR.')
            ->success()
            ->send();
    }

    #[Computed]
    public function briefingData(): array
    {
        $userId = auth()->id();
        $branchId = auth()->user()?->branch_id;
        $result = [];

        foreach (BriefingPeriod::cases() as $period) {
            $record = BriefingRecord::where('user_id', $userId)
                ->where('period', $period->value)
                ->whereDate('record_date', $period->recordDate())
                ->with('items')
                ->first();

            $tasks = BriefingTask::forPeriod($period, $branchId);
            $itemMap = $record
                ? $record->items->keyBy(fn (BriefingItem $i) => $i->task_key)
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
                    'isPastDeadline' => $task->isPastDeadline() && ! ($item?->is_completed),
                    'deadlineLabel' => $task->deadlineLabel(),
                ];
            })->all();

            $completedCount = collect($taskList)->where('isCompleted', true)->count();

            $weekLabel = null;
            if ($period === BriefingPeriod::Weekly) {
                $start = now()->startOfWeek();
                $end = now()->endOfWeek();
                $weekNum = (int) ceil($start->day / 7);
                $range = $start->month === $end->month
                    ? $start->format('j').'–'.$end->format('j M Y')
                    : $start->format('j M').'–'.$end->format('j M Y');
                $weekLabel = "Periode {$weekNum} • {$range}";
            }

            $result[$period->value] = [
                'period' => $period,
                'label' => $period->getLabel(),
                'periodLabel' => $period->periodLabel(),
                'weekLabel' => $weekLabel,
                'total' => count($taskList),
                'completed' => $completedCount,
                'tasks' => $taskList,
            ];
        }

        return $result;
    }
}
