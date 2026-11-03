<?php

namespace App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages;

use App\Enums\VendorComplianceLiabilityStatus;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\VendorComplianceIncidentResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EditVendorComplianceIncident extends EditRecord
{
    protected static string $resource = VendorComplianceIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * The follow-up form only changes handling fields. A Late Delivery candidate cannot be resolved
     * before Purchasing confirms or waives it (PRD §13.2).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->liability_status === VendorComplianceLiabilityStatus::Pending && ($data['status'] ?? null) === 'resolved') {
            throw ValidationException::withMessages(['data.status' => 'Konfirmasi atau kecualikan keterlambatan sebelum menandai insiden selesai.']);
        }
        $data = Arr::only($data, ['status', 'action_type', 'follow_up_notes']);

        $data['handled_by'] = auth()->id();
        $data['resolved_at'] = $data['status'] === 'resolved' ? ($this->record->resolved_at ?? now()) : null;

        return $data;
    }
}
