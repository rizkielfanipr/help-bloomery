<?php

namespace App\Filament\Driver\Pages;

use App\Enums\TripStatus;
use App\Models\FuelType;
use App\Models\Trip;
use App\Models\TripFuelFillup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class FuelFillup extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::getSlug($panel).'/{trip}';
    }

    public function getTitle(): string
    {
        return 'Pengisian BBM';
    }

    protected static string $layout = 'filament.driver.layouts.bare';

    protected string $view = 'filament.driver.pages.fuel-fillup';

    public int $trip;

    public string $fuelType = '';

    public int $pricePerLiter = 0;

    public string $liters = '';

    public string $totalPrice = '';

    public string $spbuAddress = '';

    public $notaPhoto = null;

    public $indicatorPhoto = null;

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
        return Trip::where('driver_id', auth()->id())->findOrFail($this->trip);
    }

    #[Computed]
    public function fuelTypes(): Collection
    {
        return FuelType::active()->get();
    }

    public function submit(): void
    {
        $this->validate([
            'fuelType' => 'required',
            'liters' => 'required|numeric|min:0.1',
            'totalPrice' => 'required|numeric|min:0',
            'spbuAddress' => 'required|string|max:255',
            'notaPhoto' => 'required',
            'indicatorPhoto' => 'required',
        ]);

        $notaPath = $this->notaPhoto->store('trip-fuel', 'b2');
        $indicatorPath = $this->indicatorPhoto->store('trip-fuel', 'b2');

        TripFuelFillup::create([
            'trip_id' => $this->trip,
            'fuel_type' => $this->fuelType,
            'liters' => $this->liters,
            'price_per_liter' => $this->pricePerLiter ?: null,
            'total_price' => $this->totalPrice,
            'spbu_address' => strtoupper($this->spbuAddress),
            'attachment_path' => $notaPath,
            'fuel_indicator_photo' => $indicatorPath,
        ]);

        Trip::where('id', $this->trip)
            ->where('driver_id', auth()->id())
            ->update([
                'status' => TripStatus::Completed,
                'completed_at' => now(),
                'has_fuel_fillup' => true,
            ]);

        Notification::make()
            ->title('Perjalanan selesai!')
            ->body('Laporan perjalanan dan pengisian BBM telah tersimpan.')
            ->success()
            ->send();

        $this->redirect(TripHistory::getUrl());
    }
}
