<?php

namespace App\Filament\Helpdesk\Resources\DesignRequests\Pages;

use App\Enums\DesignRequestStatus;
use App\Filament\Helpdesk\Resources\DesignRequests\DesignRequestResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ViewDesignRequest extends ViewRecord
{
    protected static string $resource = DesignRequestResource::class;

    protected string $view = 'filament.helpdesk.design-requests.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $status = '';

    public string $adminNotes = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['requester', 'branch', 'category', 'statusHistories.changedBy']);
        $this->status = $this->record->status->value;
        $this->adminNotes = '';
    }

    public function saveFollowUp(): void
    {
        abort_unless(auth()->user()?->can('edit design requests'), 403);
        $currentStatus = $this->record->status;
        $data = $this->validate([
            'status' => ['required', Rule::enum(DesignRequestStatus::class)],
            'adminNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $newStatus = DesignRequestStatus::from($data['status']);
        if ($newStatus !== $currentStatus && ! $currentStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Status hanya dapat diubah mengikuti urutan proses.',
            ]);
        }

        if ($newStatus === DesignRequestStatus::Rejected && blank(trim($data['adminNotes'] ?? ''))) {
            throw ValidationException::withMessages([
                'adminNotes' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        $this->record->update([
            'status' => $data['status'],
            'admin_notes' => trim($data['adminNotes']) ?: null,
            'resolved_at' => $newStatus === DesignRequestStatus::Completed ? ($this->record->resolved_at ?? now()) : null,
        ]);
        $this->record->refresh()->load(['requester', 'branch', 'category', 'statusHistories.changedBy']);
        $this->adminNotes = '';

        Notification::make()->title('Tindak lanjut design disimpan')->success()->send();
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
            ->mapWithKeys(fn (DesignRequestStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Detail Permintaan Design';
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
