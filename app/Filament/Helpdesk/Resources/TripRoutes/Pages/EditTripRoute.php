<?php

namespace App\Filament\Helpdesk\Resources\TripRoutes\Pages;

use App\Filament\Helpdesk\Resources\TripRoutes\TripRouteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTripRoute extends EditRecord
{
    protected static string $resource = TripRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
