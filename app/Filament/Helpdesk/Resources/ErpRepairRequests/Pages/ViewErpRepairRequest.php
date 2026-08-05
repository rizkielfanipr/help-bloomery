<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages;

use App\Enums\ItRequestStatus;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\ErpRepairRequestResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ViewErpRepairRequest extends ViewRecord
{
    protected static string $resource = ErpRepairRequestResource::class;

    protected string $view = 'filament.helpdesk.erp-repair-requests.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $status = '';

    public string $itNotes = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->reloadRecord();
        $this->fillFollowUp();
    }

    public function getTitle(): string
    {
        return $this->record->ticket_number.' — '.$this->record->module?->name;
    }

    public function saveFollowUp(): void
    {
        $this->authorizeFollowUp();
        $data = $this->validate([
            'status' => ['required', Rule::enum(ItRequestStatus::class)],
            'itNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $status = ItRequestStatus::from($data['status']);

        if ($status !== $this->record->status && ! $this->record->status->canTransitionTo($status)) {
            $this->addError('status', 'Status must follow the configured workflow sequence.');

            return;
        }

        if ($status === ItRequestStatus::Rejected && blank(trim($data['itNotes'] ?? ''))) {
            $this->addError('itNotes', 'Alasan penolakan wajib diisi.');

            return;
        }

        $previousStatus = $this->record->status->value;

        DB::transaction(function () use ($data, $status, $previousStatus): void {
            $this->record->update([
                'status' => $status,
                'it_notes' => trim($data['itNotes']) ?: null,
                'resolved_at' => $status === ItRequestStatus::Completed ? ($this->record->resolved_at ?? now()) : $this->record->resolved_at,
                'closed_by' => $status === ItRequestStatus::Completed ? ($this->record->closed_by ?? auth()->id()) : $this->record->closed_by,
            ]);

            $this->record->activities()->create([
                'actor_id' => auth()->id(),
                'action' => $previousStatus === $status->value ? 'follow_up_updated' : 'status_changed',
                'from_status' => $previousStatus,
                'to_status' => $status->value,
                'notes' => trim($data['itNotes']) ?: null,
            ]);
        });

        $this->reloadRecord();
        $this->fillFollowUp();
        Notification::make()->title('Follow-up saved')->success()->send();
    }

    public function canFollowUp(): bool
    {
        return (auth()->user()?->can('edit erp requests') ?? false)
            && ! in_array($this->record->status, [ItRequestStatus::Completed, ItRequestStatus::Rejected], true);
    }

    /** @return array<string, string> */
    public function nextStatusOptions(): array
    {
        return collect($this->record->status->allowedTransitions())
            ->mapWithKeys(fn (ItRequestStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    public function transitionTo(string $status): void
    {
        $target = ItRequestStatus::tryFrom($status);
        abort_unless($target && array_key_exists($target->value, $this->nextStatusOptions()), 409);

        $this->status = $target->value;
        $this->saveFollowUp();
    }

    private function authorizeFollowUp(): void
    {
        abort_unless($this->canFollowUp(), 403);
    }

    private function fillFollowUp(): void
    {
        $this->status = $this->record->status->value;
        $this->itNotes = '';
    }

    private function reloadRecord(): void
    {
        $this->record->refresh()->load([
            'requester', 'branch', 'module', 'requestType',
            'closedBy', 'activities.actor',
        ]);
    }
}
