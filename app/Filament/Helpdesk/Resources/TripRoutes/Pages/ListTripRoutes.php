<?php

namespace App\Filament\Helpdesk\Resources\TripRoutes\Pages;

use App\Filament\Helpdesk\Resources\TripRoutes\TripRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTripRoutes extends ListRecords
{
    protected static string $resource = TripRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
