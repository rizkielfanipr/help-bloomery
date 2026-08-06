<?php

namespace App\Filament\Helpdesk\Resources\ContentRequests\Pages;

use App\Enums\ContentRequestStatus;
use App\Filament\Helpdesk\Resources\ContentRequests\ContentRequestResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ViewContentRequest extends ViewRecord
{
    protected static string $resource = ContentRequestResource::class;

    protected string $view = 'filament.helpdesk.content-requests.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $status = '';

    public string $adminNotes = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['requester', 'branch', 'statusHistories.changedBy']);
        $this->status = $this->record->status->value;
        $this->adminNotes = '';
    }

    public function saveFollowUp(): void
    {
        abort_unless(auth()->user()?->can('edit content requests'), 403);
        $currentStatus = $this->record->status;
        $data = $this->validate([
            'status' => ['required', Rule::enum(ContentRequestStatus::class)],
            'adminNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $newStatus = ContentRequestStatus::from($data['status']);
        if ($newStatus !== $currentStatus && ! $currentStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Status hanya dapat diubah mengikuti urutan proses.',
            ]);
        }

        if ($newStatus === ContentRequestStatus::Rejected && blank(trim($data['adminNotes'] ?? ''))) {
            throw ValidationException::withMessages([
                'adminNotes' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        $this->record->update([
            'status' => $data['status'],
            'admin_notes' => trim($data['adminNotes']) ?: null,
            'resolved_at' => $newStatus === ContentRequestStatus::Completed ? ($this->record->resolved_at ?? now()) : null,
        ]);
        $this->record->refresh()->load(['requester', 'branch', 'statusHistories.changedBy']);
        $this->adminNotes = '';

        Notification::make()->title('Tindak lanjut konten disimpan')->success()->send();
    }

    public function transitionTo(string $status): void
    {
        $this->status = $status;
        $this->saveFollowUp();
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return collect($this->record->status->allowedTransitions())
            ->mapWithKeys(fn (ContentRequestStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Detail Permintaan Konten';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
