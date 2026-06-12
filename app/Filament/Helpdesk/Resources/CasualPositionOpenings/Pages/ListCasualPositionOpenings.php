<?php

namespace App\Filament\Helpdesk\Resources\CasualPositionOpenings\Pages;

use App\Filament\Helpdesk\Resources\CasualPositionOpenings\CasualPositionOpeningResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCasualPositionOpenings extends ListRecords
{
    protected static string $resource = CasualPositionOpeningResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Lowongan Posisi';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola lowongan posisi casual yang dibuka untuk pendaftaran';
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
