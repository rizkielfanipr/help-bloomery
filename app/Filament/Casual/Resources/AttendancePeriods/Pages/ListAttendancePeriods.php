<?php

namespace App\Filament\Casual\Resources\AttendancePeriods\Pages;

use App\Filament\Casual\Resources\AttendancePeriods\AttendancePeriodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendancePeriods extends ListRecords
{
    protected static string $resource = AttendancePeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
