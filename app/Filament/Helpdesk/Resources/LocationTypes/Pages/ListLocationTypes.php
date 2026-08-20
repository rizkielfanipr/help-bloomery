<?php

namespace App\Filament\Helpdesk\Resources\LocationTypes\Pages;

use App\Filament\Helpdesk\Resources\LocationTypes\LocationTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListLocationTypes extends ListRecords
{
    protected static string $resource = LocationTypeResource::class;
}
