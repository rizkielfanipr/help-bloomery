<?php

namespace App\Filament\Helpdesk\Resources\ErpRepairRequests\Pages;

use App\Enums\ItRequestStatus;
use App\Filament\Helpdesk\Resources\ErpRepairRequests\ErpRepairRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditErpRepairRequest extends EditRecord
{
    protected static string $resource = ErpRepairRequestResource::class;

    private ?string $previousStatus = null;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function beforeSave(): void
    {
        $this->previousStatus = $this->record->status->value;

        $target = ItRequestStatus::from((string) $this->data['status']);
        if (! $this->record->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'data.status' => 'Status must follow the configured workflow sequence.',
            ]);
        }
    }

    protected function afterSave(): void
    {
        $newStatus = $this->record->status->value;
        $action = $newStatus !== $this->previousStatus ? 'status_changed' : 'updated';

        if ($this->record->status === ItRequestStatus::Completed && ! $this->record->resolved_at) {
            $this->record->updateQuietly([
                'resolved_at' => now(),
                'closed_by' => auth()->id(),
            ]);
        }

        $this->record->activities()->create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'from_status' => $this->previousStatus,
            'to_status' => $newStatus,
            'notes' => $this->record->it_notes,
        ]);
    }
}
