<?php

namespace App\Filament\Driver\Pages;

use App\Enums\TripStatus;
use App\Models\Trip;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class TripDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    protected static string $layout = 'filament.driver.layouts.bare';

    protected string $view = 'filament.driver.pages.trip-dashboard';

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
