<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Helpdesk\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\TechnicianSettings;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $max = TechnicianSettings::instance()->max_jobs_per_day;
        $booked = ServiceRequest::whereDate('scheduled_date', $data['scheduled_date'])->count();

        if ($booked >= $max) {
            Notification::make()
                ->title('Kuota penjadwalan penuh')
                ->body("Tanggal {$data['scheduled_date']} sudah mencapai batas {$max} pekerjaan. Silakan pilih tanggal lain.")
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        $data['status'] = ServiceRequestStatus::Submitted->value;
        $data['scheduled_by'] = auth()->id();

        return $data;
    }
}
