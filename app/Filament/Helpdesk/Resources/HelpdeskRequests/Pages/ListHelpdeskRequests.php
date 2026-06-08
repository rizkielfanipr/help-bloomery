<?php

namespace App\Filament\Helpdesk\Resources\HelpdeskRequests\Pages;

use App\Filament\Helpdesk\Resources\HelpdeskRequests\HelpdeskRequestResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListHelpdeskRequests extends ListRecords
{
    protected static string $resource = HelpdeskRequestResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Permintaan Helpdesk';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola dan pantau permintaan helpdesk';
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
