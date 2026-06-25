<?php

namespace App\Filament\Casual\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Casual\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.resources.service-requests.pages.list-service-requests';

    #[Computed]
    public function serviceRequests(): Collection
    {
        return ServiceRequestResource::getEloquentQuery()
            ->whereIn('status', [
                ServiceRequestStatus::Submitted,
                ServiceRequestStatus::InProgress,
                ServiceRequestStatus::ReSubmitted,
            ])
            ->orderBy('scheduled_date', 'asc')
            ->get();
    }
}
