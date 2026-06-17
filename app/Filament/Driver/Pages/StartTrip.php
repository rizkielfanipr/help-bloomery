<?php

namespace App\Filament\Driver\Pages;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class StartTrip extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationLabel = 'Mulai Perjalanan';

    protected static ?int $navigationSort = 1;

    protected static string $layout = 'filament.driver.layouts.bare';

    protected string $view = 'filament.driver.pages.start-trip';

    public ?array $data = [];

    public function mount(): void
    {
        // Cek apakah sudah ada trip aktif
        $activeTrip = Trip::where('driver_id', auth()->id())
            ->where('status', TripStatus::InProgress)
            ->first();

        if ($activeTrip) {
            $this->redirect(ActiveTrip::getUrl(['trip' => $activeTrip->id]));

            return;
        }

        $this->form->fill([
            'trip_date' => today()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Perjalanan')
                    ->schema([
                        Select::make('trip_route_id')
                            ->label('Rute Perjalanan')
                            ->options(
                                TripRoute::where('is_active', true)
                                    ->withCount('waypoints')
                                    ->get()
                                    ->mapWithKeys(fn ($route) => [
                                        $route->id => $route->name.' ('.$route->waypoints_count.' titik)',
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->live()
                            ->helperText(function ($state): ?string {
                                if (! $state) {
                                    return null;
                                }

                                $route = TripRoute::find($state);

                                if ($route?->meal_allowance_amount) {
                                    return 'Uang makan: Rp '.number_format($route->meal_allowance_amount, 0, ',', '.');
                                }

                                return null;
                            }),

                        DatePicker::make('trip_date')
                            ->label('Tanggal Perjalanan')
                            ->required()
                            ->default(today())
                            ->maxDate(today()),

                        Select::make('vehicle_id')
                            ->label('Kendaraan')
                            ->options(
                                Vehicle::where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn ($v) => [
                                        $v->id => $v->license_plate.' - '.$v->brand.' '.$v->model.' ('.$v->year.')',
                                    ])
                            )
                            ->required()
                            ->searchable(),

                        Textarea::make('notes')
                            ->label('Catatan (opsional)')
                            ->nullable()
                            ->rows(2),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function startTrip(): void
    {
        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            $route = TripRoute::with('waypoints')->findOrFail($data['trip_route_id']);

            $trip = Trip::create([
                'driver_id' => auth()->id(),
                'vehicle_id' => $data['vehicle_id'],
                'trip_route_id' => $data['trip_route_id'],
                'trip_date' => $data['trip_date'],
                'status' => TripStatus::InProgress,
                'started_at' => now(),
                'meal_allowance_amount' => $route->meal_allowance_amount,
                'notes' => $data['notes'] ?? null,
            ]);

            // Buat checkin kosong untuk setiap waypoint
            foreach ($route->waypoints as $waypoint) {
                $trip->waypointCheckins()->create([
                    'trip_route_waypoint_id' => $waypoint->id,
                ]);
            }
        });

        $activeTrip = Trip::where('driver_id', auth()->id())
            ->where('status', TripStatus::InProgress)
            ->latest()
            ->first();

        Notification::make()
            ->title('Perjalanan dimulai!')
            ->success()
            ->send();

        $this->redirect(ActiveTrip::getUrl(['trip' => $activeTrip->id]));
    }
}
