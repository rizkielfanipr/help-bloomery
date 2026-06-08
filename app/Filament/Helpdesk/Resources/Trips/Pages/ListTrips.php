<?php

namespace App\Filament\Helpdesk\Resources\Trips\Pages;

use App\Filament\Helpdesk\Resources\Trips\TripResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Monitoring Perjalanan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau dan kelola data perjalanan driver';
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
