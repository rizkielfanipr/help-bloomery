<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Helpdesk\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\TechnicianSettings;
use App\Services\AssetServiceRequestAssigner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->canAccessBranch((int) ($data['branch_id'] ?? 0))) {
            throw ValidationException::withMessages(['data.branch_id' => 'Pilih cabang yang dapat Anda akses.']);
        }

        $max = TechnicianSettings::instance()->max_jobs_per_day;
        $date = $data['scheduled_date'] ?? null;
        $booked = $date ? ServiceRequest::whereDate('scheduled_date', $date)->count() : 0;

        if (! empty($data['scheduled_date']) && $booked >= $max) {
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

    protected function afterCreate(): void
    {
        app(AssetServiceRequestAssigner::class)->assign($this->record->load(['asset', 'branch']));
    }
}
