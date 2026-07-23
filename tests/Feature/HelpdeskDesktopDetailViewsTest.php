<?php

use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Enums\RequestStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TripStatus;
use App\Filament\Helpdesk\Resources\DesignRequests\Pages\ViewDesignRequest;
use App\Filament\Helpdesk\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Filament\Helpdesk\Resources\Trips\Pages\ViewTrip;
use App\Models\Branch;
use App\Models\DesignCategory;
use App\Models\DesignRequest;
use App\Models\PurchaseRequest;
use App\Models\ServiceRequest;
use App\Models\Trip;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->admin = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->admin->assignRole('SUPERADMIN');
    $this->actingAs($this->admin);
    $this->branch = Branch::factory()->create();
});

it('renders the service request desktop detail workspace', function () {
    $request = ServiceRequest::query()->forceCreate([
        'scheduled_by' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'scheduled_date' => today(),
        'requestor_notes' => 'Mesin perlu diperiksa.',
        'status' => ServiceRequestStatus::Submitted,
    ]);

    Livewire::test(ViewServiceRequest::class, ['record' => $request->id])
        ->assertSee('Detail Permintaan')
        ->assertSee('Riwayat Perbaikan')
        ->assertDontSee('Tindak Lanjut');
});

it('renders the driver trip desktop detail workspace', function () {
    $vehicle = Vehicle::factory()->create();
    $route = TripRoute::factory()->create(['name' => 'Atelier - Warehouse']);
    $trip = Trip::create([
        'driver_id' => $this->admin->id,
        'vehicle_id' => $vehicle->id,
        'trip_route_id' => $route->id,
        'trip_date' => today(),
        'status' => TripStatus::Pending,
        'has_fuel_fillup' => false,
        'meal_allowance_amount' => 25_000,
    ]);

    Livewire::test(ViewTrip::class, ['record' => $trip->id])
        ->assertSee('Atelier - Warehouse')
        ->assertSee('Titik Perjalanan')
        ->assertSee('Ringkasan Operasional')
        ->assertDontSee('Tindak Lanjut Perjalanan');
});

it('renders the design request desktop detail workspace', function () {
    $category = DesignCategory::create(['name' => 'Social Media', 'sort_order' => 1, 'is_active' => true]);
    $request = DesignRequest::create([
        'requester_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'design_category_id' => $category->id,
        'judul_permintaan' => 'Banner Promo',
        'ringkasan_brief' => 'Buat banner promo bulan ini.',
        'status' => RequestStatus::Submitted,
    ]);

    Livewire::test(ViewDesignRequest::class, ['record' => $request->id])
        ->assertSee('Design Brief')
        ->assertSee('Tindak Lanjut Design')
        ->assertSee('Activity Timeline')
        ->set('assigneeId', (string) $this->admin->id)
        ->set('status', RequestStatus::InProgress->value)
        ->call('saveFollowUp')
        ->assertHasNoErrors();

    expect($request->refresh()->status)->toBe(RequestStatus::InProgress)
        ->and($request->assignee_id)->toBe($this->admin->id);
});

it('renders the purchasing request desktop detail workspace', function () {
    $request = PurchaseRequest::create([
        'user_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'division' => 'IT',
        'item_name' => 'Laptop',
        'quantity' => 1,
        'purchase_reason' => 'Penggantian perangkat kerja.',
        'purchase_type' => PurchaseType::New,
        'status' => PurchaseRequestStatus::Submitted,
    ]);

    Livewire::test(ViewPurchaseRequest::class, ['record' => $request->id])
        ->assertSee('Detail Pengajuan')
        ->assertSee('Tindak Lanjut Purchasing')
        ->assertSee('Progress Pembelian')
        ->set('status', PurchaseRequestStatus::InProcess->value)
        ->set('adminNotes', 'Sedang meminta penawaran vendor.')
        ->call('saveFollowUp')
        ->assertHasNoErrors();

    expect($request->refresh()->status)->toBe(PurchaseRequestStatus::InProcess)
        ->and($request->processed_by)->toBe($this->admin->id);
});
