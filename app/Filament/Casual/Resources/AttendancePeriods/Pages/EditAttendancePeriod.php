<?php

namespace App\Filament\Casual\Resources\AttendancePeriods\Pages;

use App\Filament\Casual\Resources\AttendancePeriods\AttendancePeriodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendancePeriod extends EditRecord
{
    protected static string $resource = AttendancePeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
