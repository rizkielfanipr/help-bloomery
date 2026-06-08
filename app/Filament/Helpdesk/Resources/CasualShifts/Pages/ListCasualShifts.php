<?php

namespace App\Filament\Helpdesk\Resources\CasualShifts\Pages;

use App\Filament\Helpdesk\Resources\CasualShifts\CasualShiftResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCasualShifts extends ListRecords
{
    protected static string $resource = CasualShiftResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Data Shift';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola jadwal shift staff casual dengan mudah';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
