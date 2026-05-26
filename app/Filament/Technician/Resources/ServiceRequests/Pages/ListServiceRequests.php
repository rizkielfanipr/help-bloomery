<?php

namespace App\Filament\Technician\Resources\ServiceRequests\Pages;

use App\Filament\Technician\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;
}
