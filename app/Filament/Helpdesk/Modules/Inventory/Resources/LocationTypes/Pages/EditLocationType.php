<?php

namespace App\Filament\Helpdesk\Modules\Inventory\Resources\LocationTypes\Pages;

use App\Filament\Helpdesk\Modules\Inventory\Resources\LocationTypes\LocationTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditLocationType extends EditRecord
{
    protected static string $resource = LocationTypeResource::class;
}
