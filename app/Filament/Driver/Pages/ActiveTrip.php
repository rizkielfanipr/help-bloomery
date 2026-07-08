<?php

namespace App\Filament\Driver\Pages;

use App\Enums\TripStatus;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use App\Models\TripWaypointCheckin;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
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

    protected static string $layout = 'filament.driver.layouts.bare';

    protected string $view = 'filament.driver.pages.active-trip';

    public int $trip;

    /** Checkin form */
    public ?array $checkinData = [];

    /** Selected checkin ID being edited */
    public ?int $editingCheckinId = null;

    /** Increments on each modal open to force FileUpload re-mount */
    public int $checkinModalKey = 0;

    /** Show checkin modal */
    public bool $showCheckinModal = false;

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

    public function checkinForm(Schema $schema): Schema
    {
        $requiresAttachment = $this->tripModel->tripRoute->requires_waypoint_attachment;

        return $schema
            ->components([
                Hidden::make('checkin_id'),

                FileUpload::make('attachment_path')
                    ->label('Upload Bukti / Foto')
                    ->image()
                    ->disk('b2')
                    ->directory('trip-checkins')
                    ->imageEditor()
                    ->required($requiresAttachment)
                    ->helperText($requiresAttachment ? 'Wajib upload bukti di titik ini' : 'Opsional'),
            ])
            ->statePath('checkinData');
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
        $this->dispatch('close-modal', id: 'odo-start-modal');
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
        $this->dispatch('close-modal', id: 'odo-end-modal');

        Notification::make()
            ->title('Odometer akhir tersimpan!')
            ->success()
            ->send();
    }

    public function openCheckinModal(int $checkinId): void
    {
        $checkin = TripWaypointCheckin::find($checkinId);

        // Do NOT pre-populate attachment_path with the DB path — FilePond can't
        // handle a bare storage path as an existing file and will throw a JS error.
        // The saveCheckin() fallback preserves the existing attachment if no new
        // file is uploaded.
        $this->checkinData = [
            'checkin_id' => $checkinId,
            'attachment_path' => null,
        ];

        $this->editingCheckinId = $checkinId;
        $this->checkinModalKey++;
        $this->dispatch('open-modal', id: 'checkin-modal');
    }

    public function cancelCheckinModal(): void
    {
        $this->checkinData = [];
        $this->editingCheckinId = null;
        $this->dispatch('close-modal', id: 'checkin-modal');
    }

    public function saveCheckin(): void
    {
        $data = $this->checkinForm->getState();
        $checkinId = $data['checkin_id'] ?? $this->editingCheckinId;

        $checkin = TripWaypointCheckin::findOrFail($checkinId);

        // Pastikan checkin milik trip ini
        abort_unless($checkin->trip_id === $this->trip, 403);

        $checkin->update([
            'checked_in_at' => $checkin->checked_in_at ?? now(),
            'attachment_path' => $data['attachment_path'] ?? $checkin->attachment_path,
        ]);

        $this->dispatch('close-modal', id: 'checkin-modal');
        $this->checkinData = [];
        $this->editingCheckinId = null;

        // Reset komputasi
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

        $settings = $this->settings;

        if (! $settings->show_fuel_modal) {
            $this->completeTrip(hasFuel: false);

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
