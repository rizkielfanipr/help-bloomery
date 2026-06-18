<?php

namespace App\Filament\Casual\Pages;

use App\Enums\BriefingPeriod;
use App\Enums\BriefingTaskKey;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class DailyBriefingPage extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.daily-briefing-page';

    public ?array $taskData = [];

    public ?string $activeTaskKey = null;

    public int $taskModalKey = 0;

    public function getTitle(): string|Htmlable
    {
        return new HtmlString('');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function taskForm(Schema $schema): Schema
    {
        $requiresPhoto = $this->activeTaskKey
            ? BriefingTaskKey::from($this->activeTaskKey)->requiresPhoto()
            : false;

        return $schema
            ->components([
                FileUpload::make('photo_path')
                    ->label('Upload Foto / Bukti')
                    ->image()
                    ->disk('public')
                    ->directory('briefing-photos')
                    ->imageEditor()
                    ->required($requiresPhoto)
                    ->helperText($requiresPhoto ? 'Wajib upload foto sebagai bukti' : 'Opsional'),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->nullable(),
            ])
            ->statePath('taskData');
    }

    public function openTaskModal(string $taskKey): void
    {
        $this->activeTaskKey = $taskKey;
        $this->taskData = ['photo_path' => null, 'notes' => null];
        $this->taskModalKey++;
        $this->dispatch('open-task-modal');
    }

    public function saveTask(): void
    {
        if (! $this->activeTaskKey) {
            return;
        }

        $taskEnum = BriefingTaskKey::from($this->activeTaskKey);
        $period = $taskEnum->period();

        $data = $this->taskForm->getState();

        if ($taskEnum->requiresPhoto() && empty($data['photo_path'])) {
            Notification::make()
                ->title('Foto Diperlukan')
                ->body('Harap upload foto sebagai bukti penyelesaian tugas.')
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

        $item->fill([
            'photo_path' => $data['photo_path'] ?? $item->photo_path,
            'notes' => $data['notes'] ?? null,
            'is_completed' => ! $taskEnum->isHrChecked(),
            'completed_at' => ! $taskEnum->isHrChecked() ? now() : null,
        ])->save();

        if (! $record->submitted_at) {
            $record->update(['submitted_at' => now()]);
        }

        $this->activeTaskKey = null;
        $this->taskData = [];
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
                    'photoPath' => $item?->photo_path,
                    'notes' => $item?->notes,
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
