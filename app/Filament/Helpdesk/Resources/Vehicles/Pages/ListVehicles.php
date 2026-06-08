<?php

namespace App\Filament\Helpdesk\Resources\Vehicles\Pages;

use App\Filament\Helpdesk\Resources\Vehicles\VehicleResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Kendaraan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola data kendaraan armada dengan lengkap';
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
