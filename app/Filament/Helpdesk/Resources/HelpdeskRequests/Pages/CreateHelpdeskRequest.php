<?php

namespace App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages;

use App\Filament\Helpdesk\Resources\HelpdeskRequests\HelpdeskRequestResource;
use App\Models\HelpdeskFormTemplate;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpdeskRequest extends CreateRecord
{
    protected static string $resource = HelpdeskRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requester_id'] = auth()->id();

        if (empty($data['status'])) {
            $template = HelpdeskFormTemplate::find($data['helpdesk_form_template_id']);
            $firstStatus = $template?->statuses[0]['value'] ?? 'diajukan';
            $data['status'] = $firstStatus;
        }

        return $data;
    }
}
