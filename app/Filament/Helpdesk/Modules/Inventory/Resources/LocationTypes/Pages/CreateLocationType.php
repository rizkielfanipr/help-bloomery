<?php

namespace App\Filament\Helpdesk\Modules\Inventory\Resources\LocationTypes\Pages;

use App\Filament\Helpdesk\Modules\Inventory\Resources\LocationTypes\LocationTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocationType extends CreateRecord
{
    protected static string $resource = LocationTypeResource::class;
}
