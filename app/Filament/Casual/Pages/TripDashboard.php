<?php

namespace App\Filament\Casual\Pages;

use App\Enums\TripStatus;
use App\Models\Trip;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class TripDashboard extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.trip-dashboard';

    #[Computed]
    public function activeTrip(): ?Trip
    {
        return Trip::where('driver_id', auth()->id())
            ->where('status', TripStatus::InProgress)
            ->with(['tripRoute.waypoints', 'waypointCheckins', 'vehicle'])
            ->latest()
            ->first();
    }

    #[Computed]
    public function recentTrips(): Collection
    {
        return Trip::where('driver_id', auth()->id())
            ->where('status', TripStatus::Completed)
            ->with(['tripRoute', 'vehicle'])
            ->latest('completed_at')
            ->limit(5)
            ->get();
    }
}
