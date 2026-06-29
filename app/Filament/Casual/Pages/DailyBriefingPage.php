<?php

namespace App\Filament\Casual\Pages;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingReviewStatus;
use App\Enums\BriefingTaskKey;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
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

    public bool $activeTaskIsSelfie = false;

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
        $taskEnum = BriefingTaskKey::from($taskKey);
        $period = $taskEnum->period();
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
        $this->taskData = ['notes' => null];
        $this->cameraPhotoPaths = [];
        $this->activeTaskIsSelfie = in_array($taskKey, [
            BriefingTaskKey::DailySelfiePagi->value,
            BriefingTaskKey::DailySelfieSore->value,
        ]);
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

        $taskEnum = BriefingTaskKey::from($this->activeTaskKey);
        $period = $taskEnum->period();

        if ($taskEnum->requiresPhoto() && empty($this->cameraPhotoPaths)) {
            Notification::make()
                ->title('Foto Diperlukan')
                ->body('Harap ambil foto terlebih dahulu sebagai bukti.')
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

        $item->fill([
            'photo_paths' => ! empty($this->cameraPhotoPaths) ? $this->cameraPhotoPaths : ($item->photo_paths ?? []),
            'notes' => $this->taskData['notes'] ?? null,
            'is_completed' => ! $taskEnum->isHrChecked(),
            'completed_at' => ! $taskEnum->isHrChecked() ? now() : null,
            'review_status' => BriefingReviewStatus::Pending->value,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        if (! $record->submitted_at) {
            $record->update(['submitted_at' => now()]);
        }

        $this->activeTaskKey = null;
        $this->taskData = [];
        $this->cameraPhotoPaths = [];
        $this->dispatch('close-task-modal');

        unset($this->briefingData);

        Notification::make()
            ->title($taskEnum->isHrChecked() ? 'Data Tersimpan' : 'Tugas Selesai!')
            ->body($taskEnum->isHrChecked()
                ? 'Data akan diverifikasi oleh HR.'
                : 'Tugas berhasil ditandai selesai.')
            ->success()
            ->send();
    }

    #[Computed]
    public function briefingData(): array
    {
        $userId = auth()->id();
        $result = [];

        foreach (BriefingPeriod::cases() as $period) {
            $record = BriefingRecord::where('user_id', $userId)
                ->where('period', $period->value)
                ->whereDate('record_date', $period->recordDate())
                ->with('items')
                ->first();

            $tasks = BriefingTaskKey::forPeriod($period);
            $itemMap = $record
                ? $record->items->keyBy(fn (BriefingItem $i) => $i->task_key->value)
                : collect();

            $taskList = array_map(function (BriefingTaskKey $task) use ($itemMap): array {
                $item = $itemMap->get($task->value);

                return [
                    'key' => $task->value,
                    'label' => $task->getLabel(),
                    'noteType' => $task->noteType(),
                    'requiresPhoto' => $task->requiresPhoto(),
                    'isHrChecked' => $task->isHrChecked(),
                    'isCompleted' => $item?->is_completed ?? false,
                    'completedAt' => $item?->completed_at,
                    'photoPaths' => $item?->photo_paths ?? [],
                    'notes' => $item?->notes,
                    'reviewStatus' => $item?->review_status,
                    'rejectionReason' => $item?->rejection_reason,
                ];
            }, $tasks);

            $completedCount = collect($taskList)->where('isCompleted', true)->count();

            $result[$period->value] = [
                'period' => $period,
                'label' => $period->getLabel(),
                'periodLabel' => $period->periodLabel(),
                'total' => count($taskList),
                'completed' => $completedCount,
                'tasks' => $taskList,
            ];
        }

        return $result;
    }
}
