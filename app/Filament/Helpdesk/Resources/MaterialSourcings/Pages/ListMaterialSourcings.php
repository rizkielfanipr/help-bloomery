<?php

namespace App\Filament\Helpdesk\Resources\MaterialSourcings\Pages;

use App\Filament\Helpdesk\Resources\MaterialSourcings\MaterialSourcingResource;
use Filament\Resources\Pages\ListRecords;

class ListMaterialSourcings extends ListRecords
{
    protected static string $resource = MaterialSourcingResource::class;

    /**
     * Keep filter state inside Livewire without synchronizing it to the URL.
     *
     * @var array<string, mixed>|null
     */
    public ?array $tableFilters = null;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
