<?php

namespace App\Filament\Casual\Pages;

use App\Enums\TripStatus;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use App\Models\TripWaypointCheckin;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Validation\ValidationException;
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

    public ?float $checkinAccuracy = null;

    public ?string $checkinPhotoSource = null;

    public ?string $checkinCapturedAt = null;

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

    #[Computed]
    public function settings(): DriverTripSettings
    {
        return DriverTripSettings::instance();
    }

    public function saveOdoStart(int $km): void
    {
        $path = null;
        if ($this->odoStartPhoto) {
            $path = $this->odoStartPhoto->store('trip-odo', 'b2');
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
            $path = $this->odoEndPhoto->store('trip-odo', 'b2');
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

        $settings = $this->settings;
        $trip = $this->tripModel;
        $requiresPhoto = $settings->require_checkin_photo;
        $requiresLocationValidation = ! $trip->tripRoute->is_custom;

        if ($checkin->checked_in_at && ! $settings->allow_checkin_retake) {
            throw ValidationException::withMessages(['checkinPhoto' => 'Check-in ulang tidak diizinkan.']);
        }

        if ($settings->require_sequential_checkin) {
            $previousWaypointIds = $trip->tripRoute->waypoints
                ->where('urutan', '<', $checkin->waypoint->urutan)
                ->pluck('id');
            $completedPrevious = $trip->waypointCheckins
                ->whereIn('trip_route_waypoint_id', $previousWaypointIds)
                ->whereNotNull('checked_in_at')
                ->count();

            if ($completedPrevious !== $previousWaypointIds->count()) {
                throw ValidationException::withMessages(['activeCheckinId' => 'Selesaikan titik sebelumnya terlebih dahulu.']);
            }
        }

        if ($requiresPhoto && ! $this->checkinPhoto && ! $checkin->attachment_path) {
            throw ValidationException::withMessages(['checkinPhoto' => 'Foto check-in wajib diisi.']);
        }

        if ($requiresLocationValidation && $settings->require_checkin_location && ($this->checkinLat === null || $this->checkinLng === null)) {
            throw ValidationException::withMessages(['checkinLat' => 'Lokasi GPS wajib terdeteksi.']);
        }

        if ($requiresLocationValidation
            && $settings->checkin_max_location_accuracy !== null
            && ($this->checkinAccuracy === null || $this->checkinAccuracy > $settings->checkin_max_location_accuracy)) {
            throw ValidationException::withMessages(['checkinAccuracy' => 'Akurasi GPS belum memenuhi batas yang ditentukan.']);
        }

        if ($requiresLocationValidation && $settings->require_waypoint_radius) {
            $waypoint = $checkin->waypoint;

            if ($this->checkinLat === null || $this->checkinLng === null) {
                Notification::make()
                    ->title('Lokasi GPS belum terdeteksi')
                    ->body('Aktifkan izin lokasi dan tunggu sampai posisi GPS ditemukan sebelum melakukan check-in.')
                    ->danger()
                    ->persistent()
                    ->send();

                throw ValidationException::withMessages(['checkinLat' => 'Lokasi GPS wajib terdeteksi untuk memeriksa radius titik tujuan.']);
            }

            if ($waypoint->latitude === null || $waypoint->longitude === null) {
                throw ValidationException::withMessages(['checkinLat' => 'Pin lokasi titik perjalanan belum diatur.']);
            }

            $distance = $this->distanceInMeters(
                $this->checkinLat,
                $this->checkinLng,
                $waypoint->latitude,
                $waypoint->longitude,
            );

            if ($distance > $waypoint->radius_meters) {
                $distanceLabel = number_format($distance, 0, ',', '.');

                Notification::make()
                    ->title('Anda berada di luar radius tujuan')
                    ->body("Jarak Anda {$distanceLabel} meter dari {$waypoint->name}. Check-in hanya dapat dilakukan dalam radius {$waypoint->radius_meters} meter.")
                    ->danger()
                    ->persistent()
                    ->send();

                throw ValidationException::withMessages([
                    'checkinLat' => 'Anda berada '.$distanceLabel.' meter dari titik tujuan. Maksimal '.$waypoint->radius_meters.' meter.',
                ]);
            }
        }

        $attachmentPath = $checkin->attachment_path;

        if ($this->checkinPhoto) {
            $attachmentPath = $this->checkinPhoto->store('trip-checkins', 'b2');
        }

        $checkin->update([
            'checked_in_at' => $checkin->checked_in_at ?? now(),
            'attachment_path' => $attachmentPath,
            'latitude' => $this->checkinLat,
            'longitude' => $this->checkinLng,
            'location_accuracy' => $this->checkinAccuracy,
            'photo_source' => $this->checkinPhotoSource,
            'device_captured_at' => $this->checkinCapturedAt,
        ]);

        $this->checkinPhoto = null;
        $this->checkinLat = null;
        $this->checkinLng = null;
        $this->checkinAccuracy = null;
        $this->checkinPhotoSource = null;
        $this->checkinCapturedAt = null;
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

    private function distanceInMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $value = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($value), sqrt(1 - $value));
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
