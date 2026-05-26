<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Helpdesk\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = ServiceRequestStatus::Submitted->value;
        $data['scheduled_by'] = auth()->id();

        return $data;
    }
}
