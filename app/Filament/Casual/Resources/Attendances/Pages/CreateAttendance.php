<?php

namespace App\Filament\Casual\Resources\Attendances\Pages;

use App\Filament\Casual\Resources\Attendances\AttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();

        if (auth()->user()->hasRole('casual_staff') && ! auth()->user()->hasAnyRole(['super_admin', 'hr_staff'])) {
            $data['user_id'] = auth()->id();
        }

        return $data;
    }
}
