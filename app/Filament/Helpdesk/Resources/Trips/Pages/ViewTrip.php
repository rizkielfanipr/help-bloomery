<?php

namespace App\Filament\Helpdesk\Resources\Trips\Pages;

use App\Filament\Helpdesk\Resources\Trips\TripResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    protected string $view = 'filament.helpdesk.trips.view';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load([
            'driver', 'vehicle', 'tripRoute.waypoints',
            'waypointCheckins.waypoint', 'fuelFillup',
        ]);
    }
}
