<?php

namespace App\Filament\Helpdesk\Resources\DesignRequests\Pages;

use App\Enums\RequestStatus;
use App\Filament\Helpdesk\Resources\DesignRequests\DesignRequestResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\Rule;

class ViewDesignRequest extends ViewRecord
{
    protected static string $resource = DesignRequestResource::class;

    protected string $view = 'filament.helpdesk.design-requests.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $assigneeId = '';

    public string $status = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['requester', 'branch', 'category', 'assignee']);
        $this->assigneeId = (string) ($this->record->assignee_id ?? '');
        $this->status = $this->record->status->value;
    }

    /** @return array<int, string> */
    public function assigneeOptions(): array
    {
        return User::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function saveFollowUp(): void
    {
        abort_unless(auth()->user()?->can('edit design requests'), 403);
        $data = $this->validate([
            'assigneeId' => ['nullable', 'exists:users,id'],
            'status' => ['required', Rule::enum(RequestStatus::class)],
        ]);

        $this->record->update([
            'assignee_id' => $data['assigneeId'] ?: null,
            'status' => $data['status'],
            'resolved_at' => $data['status'] === RequestStatus::Completed->value ? ($this->record->resolved_at ?? now()) : null,
        ]);
        $this->record->refresh()->load(['requester', 'branch', 'category', 'assignee']);

        Notification::make()->title('Tindak lanjut design disimpan')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
