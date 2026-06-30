<?php

namespace App\Filament\Helpdesk\Resources\CasualOvertimeRequests\Pages;

use App\Filament\Helpdesk\Resources\CasualOvertimeRequests\CasualOvertimeRequestResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCasualOvertimeRequests extends ListRecords
{
    protected static string $resource = CasualOvertimeRequestResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Request Lembur Casual';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola dan review permintaan lembur staff casual';
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
