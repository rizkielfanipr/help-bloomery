<?php

namespace App\Filament\Helpdesk\Resources\CasualClockRecords\Pages;

use App\Filament\Helpdesk\Resources\CasualClockRecords\CasualClockRecordResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCasualClockRecords extends ListRecords
{
    protected static string $resource = CasualClockRecordResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Monitoring Absensi Casual';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau dan kelola data absensi staff casual';
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
