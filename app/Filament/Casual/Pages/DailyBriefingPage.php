<?php

namespace App\Filament\Casual\Pages;

use App\Actions\Briefing\BriefingSubmissionAction;
use App\Models\BriefingTask;
use App\Services\BriefingAssignmentService;
use App\Services\BriefingWorkspaceBuilder;
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
        $task = BriefingTask::cached()->firstWhere('key', $taskKey);

        if (! $task || ! app(BriefingAssignmentService::class)->taskIsAssignedTo(auth()->user(), $task, now())) {
            return;
        }

        $row = collect($this->briefingData[$task->period->value]['tasks'] ?? [])->firstWhere('key', $taskKey);

        if ($row === null) {
            return;
        }

        if (! $row['isEditable']) {
            Notification::make()
                ->title('Tidak Dapat Diubah')
                ->body($this->briefingData[$task->period->value]['bundle']['message'] ?? 'Poin ini tidak dapat diubah saat ini.')
                ->warning()
                ->send();

            return;
        }

        $this->activeTaskKey = $taskKey;
        $this->activeSubmissionType = $row['submissionType'];
        $this->activeTaskLabel = $row['label'];
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

    /**
     * docs/daily-briefing-bundle-approval-prd.md §12: one bundle workspace per period, built by a
     * service so the page holds no completeness or editability rules of its own.
     *
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function briefingData(): array
    {
        return app(BriefingWorkspaceBuilder::class)->forUser(auth()->user(), now());
    }
}
