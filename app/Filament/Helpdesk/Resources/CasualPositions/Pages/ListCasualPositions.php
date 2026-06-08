<?php

namespace App\Filament\Helpdesk\Resources\CasualPositions\Pages;

use App\Filament\Helpdesk\Resources\CasualPositions\CasualPositionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCasualPositions extends ListRecords
{
    protected static string $resource = CasualPositionResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Posisi Casual';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola posisi casual dengan mudah dan terstruktur';
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
