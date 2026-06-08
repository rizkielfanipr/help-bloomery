<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests\Pages;

use App\Filament\Helpdesk\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Permintaan Servis';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola dan pantau permintaan servis teknisi';
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
