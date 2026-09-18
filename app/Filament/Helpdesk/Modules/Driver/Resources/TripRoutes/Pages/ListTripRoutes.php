<?php

namespace App\Filament\Helpdesk\Modules\Driver\Resources\TripRoutes\Pages;

use App\Filament\Helpdesk\Modules\Driver\Resources\TripRoutes\TripRouteResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTripRoutes extends ListRecords
{
    protected static string $resource = TripRouteResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Rute Perjalanan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola rute perjalanan driver dengan terstruktur';
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
