<?php

use App\Enums\TripStatus;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\TripRouteWaypoint;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->driver = User::factory()->create(['is_active' => true]);
    $this->driver->assignRole('driver');
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
    $route = TripRoute::factory()->create(['requires_waypoint_attachment' => true]);
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
    expect($settings->report_cutoff_day)->toBe(20);
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
