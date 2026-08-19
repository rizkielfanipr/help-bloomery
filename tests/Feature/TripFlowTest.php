<?php

use App\Enums\TripStatus;
use App\Filament\Casual\Pages\ActiveTrip;
use App\Filament\Casual\Pages\StartTrip;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\TripRouteWaypoint;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->driver = User::factory()->create(['is_active' => true]);
    $this->driver->assignRole('DRIVER');
});

it('creates trip route with waypoints', function () {
    $route = TripRoute::create([
        'name' => 'Jakarta - Yogyakarta',
        'meal_allowance_amount' => 35000,
        'requires_waypoint_attachment' => false,
        'is_active' => true,
    ]);

    TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 1, 'name' => 'Titik A']);
    TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 2, 'name' => 'Titik B']);
    TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 3, 'name' => 'Titik C']);

    expect($route->waypoints)->toHaveCount(3);
    expect($route->meal_allowance_amount)->toBe('35000.00');
});

it('creates a trip with waypoint checkins', function () {
    $vehicle = Vehicle::create([
        'license_plate' => 'B 1234 ABC',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2022,
        'is_active' => true,
    ]);
    $route = TripRoute::factory()->create(['is_active' => true, 'requires_waypoint_attachment' => false]);
    TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 1, 'name' => 'Toko A']);
    TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 2, 'name' => 'Toko B']);

    $trip = Trip::create([
        'driver_id' => $this->driver->id,
        'vehicle_id' => $vehicle->id,
        'trip_route_id' => $route->id,
        'trip_date' => today(),
        'status' => TripStatus::InProgress,
        'started_at' => now(),
    ]);

    foreach ($route->waypoints as $waypoint) {
        $trip->waypointCheckins()->create(['trip_route_waypoint_id' => $waypoint->id]);
    }

    expect($trip->waypointCheckins)->toHaveCount(2);
    expect($trip->isInProgress())->toBeTrue();
});

it('marks allWaypointsCompleted correctly without attachment requirement', function () {
    DriverTripSettings::instance()->update(['require_checkin_photo' => false]);

    $route = TripRoute::factory()->create(['requires_waypoint_attachment' => false]);
    $wp1 = TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 1, 'name' => 'A']);
    $wp2 = TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 2, 'name' => 'B']);

    $trip = Trip::factory()->create([
        'driver_id' => $this->driver->id,
        'trip_route_id' => $route->id,
        'status' => TripStatus::InProgress,
    ]);

    $trip->waypointCheckins()->createMany([
        ['trip_route_waypoint_id' => $wp1->id, 'checked_in_at' => null],
        ['trip_route_waypoint_id' => $wp2->id, 'checked_in_at' => null],
    ]);

    $trip->load('tripRoute.waypoints', 'waypointCheckins');

    // Belum check-in semua
    expect($trip->allWaypointsCompleted())->toBeFalse();

    // Check-in semua
    $trip->waypointCheckins()->update(['checked_in_at' => now()]);
    $trip->load('tripRoute.waypoints', 'waypointCheckins');

    expect($trip->allWaypointsCompleted())->toBeTrue();
});

it('marks allWaypointsCompleted correctly with attachment requirement', function () {
    DriverTripSettings::instance()->update(['require_checkin_photo' => true]);

    $route = TripRoute::factory()->create(['requires_waypoint_attachment' => false]);
    $wp = TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 1, 'name' => 'A']);

    $trip = Trip::factory()->create([
        'driver_id' => $this->driver->id,
        'trip_route_id' => $route->id,
        'status' => TripStatus::InProgress,
    ]);

    $checkin = $trip->waypointCheckins()->create([
        'trip_route_waypoint_id' => $wp->id,
        'checked_in_at' => now(),
        'attachment_path' => null, // No attachment yet
    ]);

    $trip->load('tripRoute.waypoints', 'waypointCheckins');

    // Check-in ada tapi tanpa attachment → belum selesai
    expect($trip->allWaypointsCompleted())->toBeFalse();

    // Tambahkan attachment
    $checkin->update(['attachment_path' => 'trip-checkins/test.jpg']);
    $trip->load('tripRoute.waypoints', 'waypointCheckins');

    expect($trip->allWaypointsCompleted())->toBeTrue();
});

it('driver trip settings singleton returns defaults', function () {
    $settings = DriverTripSettings::instance();

    expect($settings->show_fuel_modal)->toBeTrue();
    expect($settings->require_fuel_attachment)->toBeTrue();
    expect($settings->custom_route_meal_allowance_amount)->toBe('0.00');
    expect($settings->checkin_photo_source)->toBe('camera');
    expect($settings->require_checkin_photo)->toBeTrue();
    expect($settings->require_checkin_location)->toBeTrue();
    expect($settings->checkin_photo_quality)->toBe(75);
    expect($settings->checkin_photo_max_dimension)->toBe(1600);
    expect($settings->report_cutoff_day)->toBe(20);
});

it('stores required checkin photo and location metadata', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->driver);
    Storage::fake('b2');

    $vehicle = Vehicle::create([
        'license_plate' => 'B 2468 GPS',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2024,
        'is_active' => true,
    ]);
    $route = TripRoute::factory()->create(['requires_waypoint_attachment' => true]);
    $waypoint = TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 1, 'name' => 'Toko A']);
    $trip = Trip::factory()->inProgress()->create([
        'driver_id' => $this->driver->id,
        'vehicle_id' => $vehicle->id,
        'trip_route_id' => $route->id,
    ]);
    $checkin = $trip->waypointCheckins()->create(['trip_route_waypoint_id' => $waypoint->id]);

    Livewire::test(ActiveTrip::class, ['trip' => $trip->id])
        ->set('activeCheckinId', $checkin->id)
        ->set('checkinPhoto', UploadedFile::fake()->image('checkin.jpg'))
        ->set('checkinLat', -7.782931)
        ->set('checkinLng', 110.367084)
        ->set('checkinAccuracy', 12.5)
        ->set('checkinPhotoSource', 'camera')
        ->set('checkinCapturedAt', '2026-08-19 14:35:21')
        ->call('saveCheckin');

    $checkin->refresh();
    expect($checkin->checked_in_at)->not->toBeNull()
        ->and($checkin->attachment_path)->not->toBeNull()
        ->and($checkin->latitude)->toBe(-7.782931)
        ->and($checkin->longitude)->toBe(110.367084)
        ->and($checkin->location_accuracy)->toBe(12.5)
        ->and($checkin->photo_source)->toBe('camera')
        ->and($checkin->device_captured_at?->format('Y-m-d H:i:s'))->toBe('2026-08-19 14:35:21');
});

it('notifies the driver when attempting checkin outside the waypoint radius', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->driver);

    DriverTripSettings::instance()->update([
        'require_checkin_photo' => false,
        'require_checkin_location' => true,
        'require_waypoint_radius' => true,
    ]);

    $vehicle = Vehicle::create([
        'license_plate' => 'B 1357 GPS',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2024,
        'is_active' => true,
    ]);
    $route = TripRoute::factory()->create();
    $waypoint = TripRouteWaypoint::create([
        'trip_route_id' => $route->id,
        'urutan' => 1,
        'name' => 'Bloomery Tugu',
        'latitude' => -7.7829,
        'longitude' => 110.3671,
        'radius_meters' => 100,
    ]);
    $trip = Trip::factory()->inProgress()->create([
        'driver_id' => $this->driver->id,
        'vehicle_id' => $vehicle->id,
        'trip_route_id' => $route->id,
    ]);
    $checkin = $trip->waypointCheckins()->create(['trip_route_waypoint_id' => $waypoint->id]);

    Livewire::test(ActiveTrip::class, ['trip' => $trip->id])
        ->set('activeCheckinId', $checkin->id)
        ->set('checkinLat', -6.1754)
        ->set('checkinLng', 106.8272)
        ->call('saveCheckin')
        ->assertHasErrors(['checkinLat'])
        ->assertNotified('Anda berada di luar radius tujuan');

    expect($checkin->refresh()->checked_in_at)->toBeNull();
});

it('allows a driver to start a custom route with reorderable waypoints and required checkin photos', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->driver);

    DriverTripSettings::instance()->update([
        'custom_route_meal_allowance_amount' => 45000,
    ]);

    $vehicle = Vehicle::create([
        'license_plate' => 'B 4321 XYZ',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2024,
        'is_active' => true,
    ]);

    Livewire::test(StartTrip::class)
        ->fillForm([
            'trip_route_id' => 'custom',
            'custom_route_name' => 'Pengiriman Tambahan Sleman',
            'custom_waypoints' => [
                ['name' => 'Titik Kedua', 'description' => 'Tujuan pertama setelah diurutkan', 'latitude' => -7.78, 'longitude' => 110.36, 'radius_meters' => 150],
                ['name' => 'Titik Pertama', 'description' => null, 'latitude' => -7.79, 'longitude' => 110.37, 'radius_meters' => 100],
            ],
            'trip_date' => today()->toDateString(),
            'vehicle_id' => $vehicle->id,
        ])
        ->call('startTrip')
        ->assertHasNoFormErrors();

    $route = TripRoute::query()->where('created_by_driver_id', $this->driver->id)->sole();
    $trip = Trip::query()->where('driver_id', $this->driver->id)->sole();

    expect($route->name)->toBe('Pengiriman Tambahan Sleman')
        ->and($route->is_custom)->toBeTrue()
        ->and($route->is_active)->toBeFalse()
        ->and($route->requires_waypoint_attachment)->toBeFalse()
        ->and($route->meal_allowance_amount)->toBe('45000.00')
        ->and($route->waypoints->pluck('name')->all())->toBe(['Titik Kedua', 'Titik Pertama'])
        ->and($route->waypoints->pluck('urutan')->all())->toBe([1, 2])
        ->and($route->waypoints->first()->latitude)->toBe(-7.78)
        ->and($route->waypoints->first()->radius_meters)->toBe(150)
        ->and($trip->trip_route_id)->toBe($route->id)
        ->and($trip->meal_allowance_amount)->toBe('45000.00')
        ->and($trip->waypointCheckins)->toHaveCount(2)
        ->and($trip->allWaypointsCompleted())->toBeFalse();
});

it('allows custom route checkin without location when global location validation is enabled', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->driver);

    DriverTripSettings::instance()->update([
        'require_checkin_photo' => false,
        'require_checkin_location' => true,
        'require_waypoint_radius' => true,
        'checkin_max_location_accuracy' => 20,
    ]);

    $vehicle = Vehicle::create([
        'license_plate' => 'B 8642 GPS',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2024,
        'is_active' => true,
    ]);

    Livewire::test(StartTrip::class)
        ->fillForm([
            'trip_route_id' => 'custom',
            'custom_route_name' => 'Rute Tanpa Validasi Lokasi',
            'custom_waypoints' => [
                ['name' => 'Titik Bebas', 'description' => null, 'radius_meters' => 100],
            ],
            'trip_date' => today()->toDateString(),
            'vehicle_id' => $vehicle->id,
        ])
        ->call('startTrip')
        ->assertHasNoFormErrors();

    $trip = Trip::query()->where('driver_id', $this->driver->id)->sole();
    $checkin = $trip->waypointCheckins()->sole();

    Livewire::test(ActiveTrip::class, ['trip' => $trip->id])
        ->set('activeCheckinId', $checkin->id)
        ->call('saveCheckin')
        ->assertHasNoErrors();

    expect($checkin->refresh()->checked_in_at)->not->toBeNull()
        ->and($checkin->latitude)->toBeNull()
        ->and($checkin->longitude)->toBeNull();
});

it('requires at least one waypoint when starting a custom route', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->driver);

    $vehicle = Vehicle::create([
        'license_plate' => 'B 9876 XYZ',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2024,
        'is_active' => true,
    ]);

    Livewire::test(StartTrip::class)
        ->fillForm([
            'trip_route_id' => 'custom',
            'custom_route_name' => 'Rute Tanpa Titik',
            'custom_waypoints' => [],
            'trip_date' => today()->toDateString(),
            'vehicle_id' => $vehicle->id,
        ])
        ->call('startTrip')
        ->assertHasFormErrors(['custom_waypoints']);

    expect(Trip::query()->where('driver_id', $this->driver->id)->exists())->toBeFalse()
        ->and(TripRoute::query()->where('created_by_driver_id', $this->driver->id)->exists())->toBeFalse();
});

it('shows one waypoint input immediately after selecting a custom route', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->driver);

    $page = Livewire::test(StartTrip::class)
        ->set('data.trip_route_id', 'custom');

    expect($page->get('data.custom_waypoints'))->toHaveCount(1);
});

it('trip report pdf route responds for driver', function () {
    $route = TripRoute::factory()->create(['is_active' => true]);
    TripRouteWaypoint::create(['trip_route_id' => $route->id, 'urutan' => 1, 'name' => 'A']);

    Trip::factory()->create([
        'driver_id' => $this->driver->id,
        'trip_route_id' => $route->id,
        'status' => TripStatus::Completed,
        'trip_date' => today(),
    ]);

    $response = $this->actingAs($this->driver)
        ->get(route('driver.trip-report.pdf', ['month' => now()->month, 'year' => now()->year]));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
});
