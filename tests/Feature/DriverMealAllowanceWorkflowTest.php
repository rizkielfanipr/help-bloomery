<?php

use App\Enums\TripStatus;
use App\Filament\Driver\Pages\TripDetail;
use App\Filament\Driver\Pages\TripHistory as DriverTripHistory;
use App\Models\DriverMealAllowancePeriod;
use App\Models\DriverTripSettings;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\User;
use App\Services\DriverMealAllowanceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->hr = User::factory()->create(['is_active' => true]);
    $this->hr->assignRole('HRD_STAFF');
    $this->hr->givePermissionTo([
        'view driver meal allowance periods', 'create driver meal allowance periods',
        'edit driver meal allowance periods', 'finalize driver meal allowance periods',
        'reopen driver meal allowance periods', 'export driver meal allowance periods',
    ]);
    $this->driver = User::factory()->create(['is_active' => true]);
    $this->driver->assignRole('DRIVER');
    $this->route = TripRoute::factory()->create(['meal_allowance_amount' => 35000]);
    DriverTripSettings::instance()->update(['report_cutoff_day' => 20]);
    $this->service = app(DriverMealAllowanceService::class);
});

it('creates a fixed cutoff period and includes only completed trips', function () {
    Trip::factory()->create([
        'driver_id' => $this->driver->id, 'trip_route_id' => $this->route->id,
        'trip_date' => '2026-07-20', 'status' => TripStatus::Completed, 'meal_allowance_amount' => 35000,
    ]);
    Trip::factory()->create([
        'driver_id' => $this->driver->id, 'trip_route_id' => $this->route->id,
        'trip_date' => '2026-08-19', 'status' => TripStatus::Completed, 'meal_allowance_amount' => 40000,
    ]);
    Trip::factory()->inProgress()->create([
        'driver_id' => $this->driver->id, 'trip_route_id' => $this->route->id, 'trip_date' => '2026-08-10',
    ]);
    Trip::factory()->create([
        'driver_id' => $this->driver->id, 'trip_route_id' => $this->route->id,
        'trip_date' => '2026-08-20', 'status' => TripStatus::Completed, 'meal_allowance_amount' => 50000,
    ]);

    $period = $this->service->createPeriod(2026, 8, $this->hr->id);

    expect($period->start_date->toDateString())->toBe('2026-07-20')
        ->and($period->end_date->toDateString())->toBe('2026-08-19')
        ->and($period->trip_count)->toBe(2)
        ->and((float) $period->total_amount)->toBe(75000.0);
});

it('applies an explained adjustment and freezes a finalized period', function () {
    Trip::factory()->create([
        'driver_id' => $this->driver->id, 'trip_route_id' => $this->route->id,
        'trip_date' => '2026-08-01', 'status' => TripStatus::Completed, 'meal_allowance_amount' => 35000,
    ]);
    $period = $this->service->createPeriod(2026, 8, $this->hr->id);
    $summary = $period->summaries()->firstOrFail();

    $this->service->updateAdjustment($summary, -5000, 'Koreksi perjalanan parsial', $this->hr->id);
    expect((float) $period->fresh()->total_amount)->toBe(30000.0);

    $this->service->finalize($period->fresh(), $this->hr->id);
    expect($period->fresh()->status)->toBe('finalized');

    expect(fn () => $this->service->sync($period->fresh()))->toThrow(ValidationException::class);
});

it('does not pay the same trip in two periods', function () {
    $trip = Trip::factory()->create([
        'driver_id' => $this->driver->id, 'trip_route_id' => $this->route->id,
        'trip_date' => '2026-08-01', 'status' => TripStatus::Completed, 'meal_allowance_amount' => 35000,
    ]);
    $august = $this->service->createPeriod(2026, 8, $this->hr->id);

    DriverMealAllowancePeriod::create([
        'report_year' => 2026, 'report_month' => 9, 'start_date' => '2026-08-01',
        'end_date' => '2026-08-31', 'created_by' => $this->hr->id,
    ]);
    $september = DriverMealAllowancePeriod::where('report_month', 9)->firstOrFail();
    $this->service->sync($september);

    expect($august->items()->where('trip_id', $trip->id)->count())->toBe(1)
        ->and($september->items()->count())->toBe(0);
});

it('requires a reason for adjustment and reopen', function () {
    Trip::factory()->create([
        'driver_id' => $this->driver->id, 'trip_route_id' => $this->route->id,
        'trip_date' => '2026-08-01', 'status' => TripStatus::Completed, 'meal_allowance_amount' => 35000,
    ]);
    $period = $this->service->createPeriod(2026, 8, $this->hr->id);
    $summary = $period->summaries()->firstOrFail();

    expect(fn () => $this->service->updateAdjustment($summary, 1000, null, $this->hr->id))->toThrow(ValidationException::class);
    $this->service->finalize($period, $this->hr->id);
    expect(fn () => $this->service->reopen($period->fresh(), '', $this->hr->id))->toThrow(ValidationException::class);
});

it('updates open period dates when cutoff changes but preserves finalized periods', function () {
    $open = $this->service->createPeriod(2026, 8, $this->hr->id);
    $finalized = DriverMealAllowancePeriod::create([
        'report_year' => 2026,
        'report_month' => 7,
        'start_date' => '2026-06-20',
        'end_date' => '2026-07-19',
        'status' => 'finalized',
        'created_by' => $this->hr->id,
    ]);

    DriverTripSettings::instance()->update(['report_cutoff_day' => 25]);
    $updated = $this->service->refreshOpenPeriods();

    expect($updated)->toBe(1)
        ->and($open->fresh()->start_date->toDateString())->toBe('2026-07-25')
        ->and($open->fresh()->end_date->toDateString())->toBe('2026-08-24')
        ->and($finalized->fresh()->start_date->toDateString())->toBe('2026-06-20')
        ->and($finalized->fresh()->end_date->toDateString())->toBe('2026-07-19');
});

it('registers meal allowance permissions without assigning them to regular roles', function () {
    expect($this->hr->can('view driver meal allowance periods'))->toBeTrue()
        ->and($this->driver->can('view driver meal allowance periods'))->toBeFalse();
});

it('renders the meal allowance link in the custom helpdesk sidebar', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $response = $this->actingAs($this->hr)->get(route('filament.helpdesk.pages.driver-meal-allowance-page'));

    $response->assertOk()
        ->assertSee('Driver')
        ->assertSee(route('filament.helpdesk.pages.driver-meal-allowance-page'), false);
});

it('shows every cutoff date in driver trip history and links existing trip details', function () {
    $trip = Trip::factory()->create([
        'driver_id' => $this->driver->id,
        'trip_route_id' => $this->route->id,
        'trip_date' => '2026-07-01',
        'status' => TripStatus::Completed,
        'meal_allowance_amount' => 35000,
    ]);
    Filament::setCurrentPanel(Filament::getPanel('driver'));
    $this->actingAs($this->driver);

    Livewire::test(DriverTripHistory::class)
        ->set('reportMonth', 7)
        ->set('reportYear', 2026)
        ->assertSee('Belum ada input')
        ->assertSee($this->route->name)
        ->assertSee(TripDetail::getUrl(['trip' => $trip->id]), false);
});
