<?php

namespace App\Filament\Employee\Modules\Technician\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Employee\Modules\Technician\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    protected static string $layout = 'filament.employee.layouts.bare';

    protected string $view = 'filament.employee.technician.pages.list-service-requests';

    #[Computed]
    public function serviceRequests(): Collection
    {
        return ServiceRequestResource::getEloquentQuery()
            ->with(['asset', 'branch'])
            ->whereIn('status', [
                ServiceRequestStatus::Submitted,
                ServiceRequestStatus::Scheduled,
                ServiceRequestStatus::AwaitingParts,
                ServiceRequestStatus::Outsource,
                ServiceRequestStatus::AwaitingVerification,
                ServiceRequestStatus::InProgress,
                ServiceRequestStatus::ReSubmitted,
            ])
            ->orderBy('scheduled_date', 'asc')
            ->get();
    }
}
