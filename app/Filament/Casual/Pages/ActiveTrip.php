<?php

namespace App\Filament\Casual\Pages;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripWaypointCheckin;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class ActiveTrip extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{trip}';
    }

    public function getTitle(): string
    {
        return 'Perjalanan Aktif';
    }

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.active-trip';

    public int $trip;

    /** Checkin sheet */
    public ?int $activeCheckinId = null;

    public $checkinPhoto = null;

    public ?float $checkinLat = null;

    public ?float $checkinLng = null;

    /** Odometer */
    public $odoStartPhoto = null;

    public $odoEndPhoto = null;

    public function mount(int $trip): void
    {
        $tripModel = Trip::where('driver_id', auth()->id())
            ->where('id', $trip)
            ->firstOrFail();

        if ($tripModel->isCompleted()) {
            $this->redirect(TripHistory::getUrl());

            return;
        }

        $this->trip = $trip;
    }

    #[Computed]
    public function tripModel(): Trip
    {
        return Trip::with([
            'tripRoute.waypoints',
            'waypointCheckins.waypoint',
            'vehicle',
        ])
            ->where('driver_id', auth()->id())
            ->findOrFail($this->trip);
    }

    public function saveOdoStart(int $km): void
    {
        $path = null;
        if ($this->odoStartPhoto) {
            $path = $this->odoStartPhoto->store('trip-odo', 'public');
            $this->odoStartPhoto = null;
        }

        Trip::where('id', $this->trip)
            ->where('driver_id', auth()->id())
            ->update([
                'odo_start' => $km,
                'odo_start_photo' => $path,
            ]);

        unset($this->tripModel);
    }

    public function saveOdoEnd(int $km): void
    {
        $trip = $this->tripModel;

        if ($trip->odo_start && $km <= $trip->odo_start) {
            Notification::make()
                ->title('Odometer tidak valid')
                ->body('Odometer akhir harus lebih besar dari odometer awal ('.$trip->odo_start.' km).')
                ->danger()
                ->send();

            return;
        }

        $path = null;
        if ($this->odoEndPhoto) {
            $path = $this->odoEndPhoto->store('trip-odo', 'public');
            $this->odoEndPhoto = null;
        }

        Trip::where('id', $this->trip)
            ->where('driver_id', auth()->id())
            ->update([
                'odo_end' => $km,
                'odo_end_photo' => $path,
            ]);

        unset($this->tripModel);

        Notification::make()
            ->title('Odometer akhir tersimpan!')
            ->success()
            ->send();
    }

    public function saveCheckin(): void
    {
        $checkin = TripWaypointCheckin::findOrFail($this->activeCheckinId);

        abort_unless($checkin->trip_id === $this->trip, 403);

        $attachmentPath = $checkin->attachment_path;

        if ($this->checkinPhoto) {
            $attachmentPath = $this->checkinPhoto->store('trip-checkins', 'public');
        }

        $checkin->update([
            'checked_in_at' => $checkin->checked_in_at ?? now(),
            'attachment_path' => $attachmentPath,
            'latitude' => $this->checkinLat,
            'longitude' => $this->checkinLng,
        ]);

        $this->checkinPhoto = null;
        $this->checkinLat = null;
        $this->checkinLng = null;
        $this->activeCheckinId = null;

        unset($this->tripModel);

        Notification::make()
            ->title('Titik berhasil di-check in!')
            ->success()
            ->send();
    }

    public function openFuelModal(): void
    {
        $trip = $this->tripModel;

        if (! $trip->allWaypointsCompleted()) {
            Notification::make()
                ->title('Belum semua titik diselesaikan')
                ->body('Selesaikan semua titik perjalanan terlebih dahulu')
                ->warning()
                ->send();

            return;
        }

        $this->dispatch('open-modal', id: 'fuel-confirm');
    }

    public function confirmNoFuel(): void
    {
        $this->completeTrip(hasFuel: false);
    }

    public function confirmHasFuel(): void
    {
        $this->redirect(FuelFillup::getUrl(['trip' => $this->trip]));
    }

    public function completeTrip(bool $hasFuel): void
    {
        Trip::where('id', $this->trip)
            ->where('driver_id', auth()->id())
            ->update([
                'status' => TripStatus::Completed,
                'completed_at' => now(),
                'has_fuel_fillup' => $hasFuel,
            ]);

        Notification::make()
            ->title('Perjalanan selesai!')
            ->body('Laporan perjalanan telah tersimpan.')
            ->success()
            ->send();

        $this->redirect(TripHistory::getUrl());
    }
}
