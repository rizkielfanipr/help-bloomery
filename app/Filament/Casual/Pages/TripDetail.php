<?php

namespace App\Filament\Casual\Pages;

use App\Models\Trip;
use Filament\Pages\Page;
use Filament\Panel;
use Livewire\Attributes\Computed;

class TripDetail extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{trip}';
    }

    public function getTitle(): string
    {
        return 'Detail Perjalanan';
    }

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.trip-detail';

    public int $trip;

    public function mount(int $trip): void
    {
        Trip::where('driver_id', auth()->id())->findOrFail($trip);
        $this->trip = $trip;
    }

    #[Computed]
    public function tripModel(): Trip
    {
        return Trip::where('driver_id', auth()->id())
            ->with(['tripRoute.waypoints', 'vehicle', 'fuelFillup', 'waypointCheckins.waypoint'])
            ->findOrFail($this->trip);
    }
}
