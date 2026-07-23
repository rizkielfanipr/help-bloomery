<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages;

use App\Enums\ItRequestStatus;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\ErpRepairRequestResource;
use App\Models\User;
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

    public string $assigneeId = '';

    public string $classification = '';

    public string $priority = 'medium';

    public string $dueAt = '';

    public string $itNotes = '';

    public string $escalationTarget = '';

    public string $escalationReason = '';

    public string $resolutionNote = '';

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

    /** @return array<int, string> */
    public function assigneeOptions(): array
    {
        return User::role(['IT_STAFF', 'SUPERADMIN'])->orderBy('name')->pluck('name', 'id')->all();
    }

    public function saveFollowUp(): void
    {
        $this->authorizeFollowUp();
        $data = $this->validate([
            'status' => ['required', Rule::enum(ItRequestStatus::class)],
            'assigneeId' => ['required', 'exists:users,id'],
            'classification' => ['required', Rule::in(['standard', 'major_project'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'dueAt' => ['nullable', 'date'],
            'itNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $status = ItRequestStatus::from($data['status']);
        if (in_array($status, [ItRequestStatus::Escalated, ItRequestStatus::Completed, ItRequestStatus::Cancelled], true)) {
            $this->addError('status', 'Use the dedicated action for this status.');

            return;
        }

        $previousStatus = $this->record->status->value;

        DB::transaction(function () use ($data, $status, $previousStatus): void {
            $this->record->update([
                'status' => $status,
                'assignee_id' => $data['assigneeId'],
                'work_classification' => $data['classification'],
                'priority' => $data['priority'],
                'due_at' => $data['dueAt'] ?: null,
                'it_notes' => trim($data['itNotes']) ?: null,
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
        Notification::make()->title('Follow-up saved')->success()->send();
    }

    public function escalate(): void
    {
        $this->authorizeFollowUp();
        $data = $this->validate([
            'assigneeId' => ['required', 'exists:users,id'],
            'classification' => ['required', Rule::in(['standard', 'major_project'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'escalationTarget' => ['required', Rule::in(['it_level_2', 'developer', 'vendor', 'other'])],
            'escalationReason' => ['required', 'string', 'max:5000'],
        ]);
        $previousStatus = $this->record->status->value;

        DB::transaction(function () use ($data, $previousStatus): void {
            $this->record->update([
                'status' => ItRequestStatus::Escalated,
                'assignee_id' => $data['assigneeId'],
                'work_classification' => $data['classification'],
                'priority' => $data['priority'],
                'escalation_target' => $data['escalationTarget'],
                'escalation_reason' => trim($data['escalationReason']),
                'escalated_at' => now(),
            ]);

            $this->record->activities()->create([
                'actor_id' => auth()->id(),
                'action' => 'escalated',
                'from_status' => $previousStatus,
                'to_status' => ItRequestStatus::Escalated->value,
                'notes' => trim($data['escalationReason']),
            ]);
        });

        $this->status = ItRequestStatus::Escalated->value;
        $this->reloadRecord();
        Notification::make()->title('Ticket escalated')->warning()->send();
    }

    public function complete(): void
    {
        $this->authorizeFollowUp();
        $data = $this->validate([
            'assigneeId' => ['required', 'exists:users,id'],
            'classification' => ['required', Rule::in(['standard', 'major_project'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'resolutionNote' => ['required', 'string', 'max:5000'],
        ]);
        $previousStatus = $this->record->status->value;

        DB::transaction(function () use ($data, $previousStatus): void {
            $this->record->update([
                'status' => ItRequestStatus::Completed,
                'assignee_id' => $data['assigneeId'],
                'work_classification' => $data['classification'],
                'priority' => $data['priority'],
                'resolution_note' => trim($data['resolutionNote']),
                'resolved_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            $this->record->activities()->create([
                'actor_id' => auth()->id(),
                'action' => 'completed',
                'from_status' => $previousStatus,
                'to_status' => ItRequestStatus::Completed->value,
                'notes' => trim($data['resolutionNote']),
            ]);
        });

        $this->status = ItRequestStatus::Completed->value;
        $this->reloadRecord();
        Notification::make()->title('Ticket completed')->success()->send();
    }

    public function canFollowUp(): bool
    {
        return (auth()->user()?->can('edit erp requests') ?? false)
            && ! in_array($this->record->status, [ItRequestStatus::Completed, ItRequestStatus::Cancelled], true);
    }

    private function authorizeFollowUp(): void
    {
        abort_unless($this->canFollowUp(), 403);
    }

    private function fillFollowUp(): void
    {
        $this->status = $this->record->status->value;
        $this->assigneeId = (string) ($this->record->assignee_id ?? '');
        $this->classification = (string) ($this->record->work_classification ?? '');
        $this->priority = (string) ($this->record->priority ?? 'medium');
        $this->dueAt = $this->record->due_at?->format('Y-m-d\TH:i') ?? '';
        $this->itNotes = (string) ($this->record->it_notes ?? '');
        $this->escalationTarget = (string) ($this->record->escalation_target ?? '');
        $this->escalationReason = (string) ($this->record->escalation_reason ?? '');
        $this->resolutionNote = (string) ($this->record->resolution_note ?? '');
    }

    private function reloadRecord(): void
    {
        $this->record->refresh()->load([
            'requester', 'branch', 'module', 'requestType', 'assignee',
            'closedBy', 'activities.actor',
        ]);
    }
}
