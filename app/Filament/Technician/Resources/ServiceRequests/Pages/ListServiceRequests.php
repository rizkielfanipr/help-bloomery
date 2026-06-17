<?php

namespace App\Filament\Technician\Resources\ServiceRequests\Pages;

use App\Filament\Technician\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    protected static string $layout = 'filament.technician.layouts.bare';

    protected string $view = 'filament.technician.resources.service-requests.pages.list-service-requests';

    #[Computed]
    public function serviceRequests(): Collection
    {
        return ServiceRequestResource::getEloquentQuery()
            ->orderBy('scheduled_date', 'asc')
            ->get();
    }
}
