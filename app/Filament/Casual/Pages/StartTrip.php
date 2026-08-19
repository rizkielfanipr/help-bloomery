<?php

namespace App\Filament\Casual\Pages;

use App\Enums\TripStatus;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class StartTrip extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return 'Mulai Perjalanan';
    }

    protected static string $layout = 'filament.casual.layouts.bare';

    protected string $view = 'filament.casual.pages.start-trip';

    public ?array $data = [];

    public function mount(): void
    {
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
                            ->options(fn (): array => TripRoute::query()
                                ->where('is_active', true)
                                ->where('is_custom', false)
                                ->withCount('waypoints')
                                ->get()
                                ->mapWithKeys(fn ($route) => [
                                    $route->id => $route->name.' ('.$route->waypoints_count.' titik)',
                                ])
                                ->put('custom', 'Rute Lain')
                                ->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $set('custom_waypoints', $state === 'custom'
                                    ? [['name' => '', 'description' => null, 'radius_meters' => DriverTripSettings::instance()->default_waypoint_radius]]
                                    : []);
                            })
                            ->searchable(),

                        TextInput::make('custom_route_name')
                            ->label('Nama Rute / Tujuan')
                            ->placeholder('Contoh: Pengiriman tambahan area Sleman')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('trip_route_id') === 'custom')
                            ->visible(fn (Get $get): bool => $get('trip_route_id') === 'custom'),

                        Repeater::make('custom_waypoints')
                            ->label('Titik Perjalanan')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Titik')
                                    ->placeholder('Contoh: Bloomery Kaliurang')
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('description')
                                    ->label('Keterangan')
                                    ->placeholder('Opsional')
                                    ->rows(2),

                                Hidden::make('latitude'),
                                Hidden::make('longitude'),
                                Hidden::make('radius_meters')->default(fn (): int => DriverTripSettings::instance()->default_waypoint_radius),
                                View::make('filament.schemas.components.waypoint-location-picker')->columnSpanFull(),
                            ])
                            ->addActionLabel('Tambah Titik')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->required(fn (Get $get): bool => $get('trip_route_id') === 'custom')
                            ->visible(fn (Get $get): bool => $get('trip_route_id') === 'custom')
                            ->reorderable()
                            ->collapsible(),

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

                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function startTrip(): void
    {
        $data = $this->form->getState();

        $existingTrip = Trip::where('driver_id', auth()->id())
            ->where('trip_date', $data['trip_date'])
            ->exists();

        if ($existingTrip) {
            Notification::make()
                ->title('Gagal memulai perjalanan')
                ->body('Anda sudah memiliki atau menyelesaikan logbook perjalanan pada tanggal tersebut.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($data) {
            if ($data['trip_route_id'] === 'custom') {
                $route = TripRoute::create([
                    'name' => $data['custom_route_name'],
                    'description' => 'Rute dinamis yang dibuat oleh driver.',
                    'meal_allowance_amount' => DriverTripSettings::instance()->custom_route_meal_allowance_amount,
                    'requires_waypoint_attachment' => false,
                    'is_custom' => true,
                    'created_by_driver_id' => auth()->id(),
                    'is_active' => false,
                ]);

                foreach (array_values($data['custom_waypoints']) as $index => $waypoint) {
                    $route->waypoints()->create([
                        'urutan' => $index + 1,
                        'name' => $waypoint['name'],
                        'description' => $waypoint['description'] ?? null,
                        'latitude' => $waypoint['latitude'] ?? null,
                        'longitude' => $waypoint['longitude'] ?? null,
                        'radius_meters' => $waypoint['radius_meters'] ?? DriverTripSettings::instance()->default_waypoint_radius,
                    ]);
                }

                $route->load('waypoints');
            } else {
                $route = TripRoute::query()
                    ->where('is_active', true)
                    ->where('is_custom', false)
                    ->with('waypoints')
                    ->findOrFail($data['trip_route_id']);
            }

            $trip = Trip::create([
                'driver_id' => auth()->id(),
                'vehicle_id' => $data['vehicle_id'],
                'trip_route_id' => $route->id,
                'trip_date' => $data['trip_date'],
                'status' => TripStatus::InProgress,
                'started_at' => now(),
                'meal_allowance_amount' => $route->meal_allowance_amount,
            ]);

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
