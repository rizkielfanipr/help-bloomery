<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests\Pages;

use App\Filament\Helpdesk\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\TechnicianSettings;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditServiceRequest extends EditRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $originalDate = $this->record->scheduled_date?->toDateString();
        $newDate = $data['scheduled_date'];

        if ($newDate !== $originalDate) {
            $max = TechnicianSettings::instance()->max_jobs_per_day;
            $booked = ServiceRequest::whereDate('scheduled_date', $newDate)
                ->where('id', '!=', $this->record->id)
                ->count();

            if ($booked >= $max) {
                Notification::make()
                    ->title('Kuota penjadwalan penuh')
                    ->body("Tanggal {$newDate} sudah mencapai batas {$max} pekerjaan. Silakan pilih tanggal lain.")
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }
}
