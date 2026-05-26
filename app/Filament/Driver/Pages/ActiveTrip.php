<?php

namespace App\Filament\Driver\Pages;

use App\Enums\TripStatus;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use App\Models\TripFuelFillup;
use App\Models\TripWaypointCheckin;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\Computed;

class ActiveTrip extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{trip}';
    }

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

    /** Show fuel modal */
    public bool $showFuelModal = false;

    /** Fuel form data */
    public ?array $fuelData = [];

    /** Has fuel toggle */
    public bool $hasFuel = false;

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
                    ->disk('public')
                    ->directory('trip-checkins')
                    ->imageEditor()
                    ->required($requiresAttachment)
                    ->helperText($requiresAttachment ? 'Wajib upload bukti di titik ini' : 'Opsional'),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->nullable()
                    ->rows(2),
            ])
            ->statePath('checkinData');
    }

    public function fuelForm(Schema $schema): Schema
    {
        $settings = $this->settings;

        return $schema
            ->components([
                Toggle::make('hasFuel')
                    ->label('Ada pengisian BBM dalam perjalanan ini?')
                    ->live()
                    ->reactive(),

                Section::make('Detail Pengisian BBM')
                    ->hidden(fn () => ! $this->hasFuel)
                    ->schema([
                        TextInput::make('spbu_address')
                            ->label('Alamat SPBU')
                            ->required()
                            ->placeholder('Contoh: SPBU 34.401.12 Jl. Magelang Km.5'),

                        Select::make('fuel_type')
                            ->label('Jenis BBM')
                            ->options([
                                'Pertalite' => 'Pertalite',
                                'Pertamax' => 'Pertamax',
                                'Pertamax Turbo' => 'Pertamax Turbo',
                                'Dexlite' => 'Dexlite',
                                'Pertamina Dex' => 'Pertamina Dex',
                                'Solar' => 'Solar',
                            ])
                            ->required(),

                        TextInput::make('liters')
                            ->label('Jumlah (Liter)')
                            ->numeric()
                            ->required()
                            ->minValue(0.1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->recalculateFuelTotal()),

                        TextInput::make('price_per_liter')
                            ->label('Harga per Liter (Rp)')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->recalculateFuelTotal()),

                        TextInput::make('total_price')
                            ->label('Total Harga (Rp)')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->helperText('Otomatis dihitung, dapat diubah manual'),

                        FileUpload::make('attachment_path')
                            ->label('Nota / Struk BBM')
                            ->image()
                            ->disk('public')
                            ->directory('trip-fuel')
                            ->imageEditor()
                            ->required($settings->require_fuel_attachment)
                            ->helperText($settings->require_fuel_attachment ? 'Wajib upload struk BBM' : 'Opsional'),
                    ])
                    ->columns(2),
            ])
            ->statePath('fuelData');
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
            'notes' => $checkin?->notes,
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
            'notes' => $data['notes'] ?? null,
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
            // Langsung selesaikan tanpa modal BBM
            $this->completeTrip(hasFuel: false);

            return;
        }

        $this->hasFuel = false;
        $this->fuelData = ['hasFuel' => false];
        $this->dispatch('open-modal', id: 'fuel-modal');
    }

    public function cancelFuelModal(): void
    {
        $this->fuelData = [];
        $this->hasFuel = false;
        $this->dispatch('close-modal', id: 'fuel-modal');
    }

    public function recalculateFuelTotal(): void
    {
        $liters = (float) ($this->fuelData['liters'] ?? 0);
        $pricePerLiter = (float) ($this->fuelData['price_per_liter'] ?? 0);

        if ($liters > 0 && $pricePerLiter > 0) {
            $this->fuelData['total_price'] = round($liters * $pricePerLiter);
        }
    }

    public function submitFuel(): void
    {
        $data = $this->fuelForm->getState();
        $hasFuel = (bool) ($data['hasFuel'] ?? false);

        if ($hasFuel) {
            TripFuelFillup::create([
                'trip_id' => $this->trip,
                'spbu_address' => $data['spbu_address'],
                'fuel_type' => $data['fuel_type'],
                'liters' => $data['liters'],
                'price_per_liter' => $data['price_per_liter'],
                'total_price' => $data['total_price'],
                'attachment_path' => $data['attachment_path'] ?? null,
            ]);
        }

        $this->completeTrip(hasFuel: $hasFuel);
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

        $this->fuelData = [];
        $this->dispatch('close-modal', id: 'fuel-modal');

        Notification::make()
            ->title('Perjalanan selesai!')
            ->body('Laporan perjalanan telah tersimpan.')
            ->success()
            ->send();

        $this->redirect(TripHistory::getUrl());
    }
}
