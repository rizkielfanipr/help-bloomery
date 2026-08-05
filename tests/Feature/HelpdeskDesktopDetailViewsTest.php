<?php

use App\Enums\DesignRequestStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Enums\ServiceRequestStatus;
use App\Enums\TripStatus;
use App\Filament\Helpdesk\Resources\DesignRequests\Pages\ViewDesignRequest;
use App\Filament\Helpdesk\Resources\PurchaseRequests\Pages\ListPurchaseRequests;
use App\Filament\Helpdesk\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Filament\Helpdesk\Resources\Trips\Pages\ViewTrip;
use App\Models\Branch;
use App\Models\DesignCategory;
use App\Models\DesignRequest;
use App\Models\PurchaseRequest;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestRepair;
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

it('preserves legacy repair photos and supports multiple repair photos', function () {
    $request = ServiceRequest::query()->forceCreate([
        'scheduled_by' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'scheduled_date' => today(),
        'status' => ServiceRequestStatus::InProgress,
    ]);

    $legacyRepair = ServiceRequestRepair::create([
        'service_request_id' => $request->id,
        'technician_id' => $this->admin->id,
        'cycle' => 1,
        'before_photo' => 'service-requests/before/legacy.jpg',
    ]);

    $repair = ServiceRequestRepair::create([
        'service_request_id' => $request->id,
        'technician_id' => $this->admin->id,
        'cycle' => 2,
        'before_photos' => ['before-1.jpg', 'before-2.jpg'],
        'after_photos' => ['after-1.jpg', 'after-2.jpg'],
    ]);

    expect($legacyRepair->before_photos)->toBe(['service-requests/before/legacy.jpg'])
        ->and($repair->before_photos)->toBe(['before-1.jpg', 'before-2.jpg'])
        ->and($repair->after_photos)->toBe(['after-1.jpg', 'after-2.jpg']);
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
        'status' => DesignRequestStatus::DesignRequest,
    ]);

    Livewire::test(ViewDesignRequest::class, ['record' => $request->id])
        ->assertSee('Ringkasan Brief')
        ->assertSee('In Progress')
        ->assertDontSee('PIC Design')
        ->assertDontSee('Design Notes')
        ->call('transitionTo', DesignRequestStatus::InProgress->value)
        ->assertHasNoErrors();

    expect($request->refresh()->status)->toBe(DesignRequestStatus::InProgress)
        ->and($request->statusHistories()->count())->toBe(2)
        ->and($request->statusHistories()->latest('id')->first()->changed_by)->toBe($this->admin->id);
});

it('only shows the Design Notes field at the approval step', function () {
    $category = DesignCategory::create(['name' => 'Social Media', 'sort_order' => 1, 'is_active' => true]);
    $request = DesignRequest::create([
        'requester_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'design_category_id' => $category->id,
        'judul_permintaan' => 'Banner Promo',
        'ringkasan_brief' => 'Buat banner promo bulan ini.',
        'status' => DesignRequestStatus::Approval,
    ]);

    Livewire::test(ViewDesignRequest::class, ['record' => $request->id])
        ->assertSee('Design Notes')
        ->set('adminNotes', 'Disetujui, lanjut ke proses cetak.')
        ->call('transitionTo', DesignRequestStatus::PrintingProcess->value)
        ->assertHasNoErrors();

    expect($request->refresh()->status)->toBe(DesignRequestStatus::PrintingProcess)
        ->and($request->statusHistories()->latest('id')->first()->notes)->toBe('Disetujui, lanjut ke proses cetak.');
});

it('requires a reason when rejecting a design request at the approval step', function () {
    $category = DesignCategory::create(['name' => 'Social Media', 'sort_order' => 1, 'is_active' => true]);
    $request = DesignRequest::create([
        'requester_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'design_category_id' => $category->id,
        'judul_permintaan' => 'Banner Promo',
        'ringkasan_brief' => 'Buat banner promo bulan ini.',
        'status' => DesignRequestStatus::Approval,
    ]);

    Livewire::test(ViewDesignRequest::class, ['record' => $request->id])
        ->set('adminNotes', '')
        ->call('transitionTo', DesignRequestStatus::Rejected->value)
        ->assertHasErrors(['adminNotes']);

    expect($request->refresh()->status)->toBe(DesignRequestStatus::Approval);
});

it('prevents skipping the design request status sequence', function () {
    $category = DesignCategory::create(['name' => 'Social Media', 'sort_order' => 1, 'is_active' => true]);
    $request = DesignRequest::create([
        'requester_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'design_category_id' => $category->id,
        'judul_permintaan' => 'Banner Promo',
        'ringkasan_brief' => 'Buat banner promo bulan ini.',
        'status' => DesignRequestStatus::DesignRequest,
    ]);

    Livewire::test(ViewDesignRequest::class, ['record' => $request->id])
        ->call('transitionTo', DesignRequestStatus::Completed->value)
        ->assertHasErrors(['status']);

    expect($request->refresh()->status)->toBe(DesignRequestStatus::DesignRequest);
});

it('walks a design request through the full sequential flow to completion', function () {
    $category = DesignCategory::create(['name' => 'Social Media', 'sort_order' => 1, 'is_active' => true]);
    $request = DesignRequest::create([
        'requester_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'design_category_id' => $category->id,
        'judul_permintaan' => 'Banner Promo',
        'ringkasan_brief' => 'Buat banner promo bulan ini.',
        'status' => DesignRequestStatus::DesignRequest,
    ]);

    $page = Livewire::test(ViewDesignRequest::class, ['record' => $request->id]);

    foreach ([
        DesignRequestStatus::InProgress,
        DesignRequestStatus::Approval,
        DesignRequestStatus::PrintingProcess,
        DesignRequestStatus::Completed,
    ] as $nextStatus) {
        $page->call('transitionTo', $nextStatus->value)->assertHasNoErrors();
        expect($request->refresh()->status)->toBe($nextStatus);
    }

    expect($request->resolved_at)->not->toBeNull()
        ->and($request->statusHistories()->count())->toBe(5);
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
        ->assertSee('Purchase Reason')
        ->assertSee('Approved')
        ->assertDontSee('Update Status')
        ->assertDontSee('Tindak Lanjut Purchasing')
        ->set('status', PurchaseRequestStatus::Approved->value)
        ->set('adminNotes', 'Sedang meminta penawaran vendor.')
        ->call('saveFollowUp')
        ->assertHasNoErrors();

    expect($request->refresh()->status)->toBe(PurchaseRequestStatus::Approved)
        ->and($request->processed_by)->toBe($this->admin->id)
        ->and($request->statusHistories()->count())->toBe(2)
        ->and($request->statusHistories()->latest('id')->first()->changed_by)->toBe($this->admin->id)
        ->and($request->statusHistories()->latest('id')->first()->notes)->toBe('Sedang meminta penawaran vendor.');
});

it('requires a reason when rejecting a purchasing request', function () {
    $request = PurchaseRequest::factory()->create([
        'user_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'status' => PurchaseRequestStatus::Submitted,
        'admin_notes' => null,
    ]);

    Livewire::test(ViewPurchaseRequest::class, ['record' => $request->id])
        ->set('status', PurchaseRequestStatus::Rejected->value)
        ->set('adminNotes', '')
        ->call('saveFollowUp')
        ->assertHasErrors(['adminNotes']);

    expect($request->refresh()->status)->toBe(PurchaseRequestStatus::Submitted);
});

it('prevents skipping the purchasing status sequence', function () {
    $request = PurchaseRequest::factory()->create([
        'user_id' => $this->admin->id,
        'branch_id' => $this->branch->id,
        'status' => PurchaseRequestStatus::Submitted,
    ]);

    Livewire::test(ViewPurchaseRequest::class, ['record' => $request->id])
        ->set('status', PurchaseRequestStatus::Purchased->value)
        ->call('saveFollowUp')
        ->assertHasErrors(['status']);

    expect($request->refresh()->status)->toBe(PurchaseRequestStatus::Submitted);
});

it('provides direct bulk status actions for selected purchasing requests', function () {
    $submitted = PurchaseRequest::factory()->count(2)->create([
        'branch_id' => $this->branch->id,
        'status' => PurchaseRequestStatus::Submitted,
    ]);
    Livewire::test(ListPurchaseRequests::class)
        ->assertSee('Cari kode...')
        ->assertSee('Cari alasan...')
        ->assertSee('- Semua Cabang -')
        ->selectTableRecords($submitted)
        ->assertTableBulkActionVisible('set_approved')
        ->assertTableBulkActionExists('set_rejected')
        ->assertTableBulkActionExists('set_purchased');
});

it('bulk updates purchasing requests using the next valid status', function () {
    $requests = PurchaseRequest::factory()->count(2)->create([
        'branch_id' => $this->branch->id,
        'status' => PurchaseRequestStatus::Approved,
    ]);

    Livewire::test(ListPurchaseRequests::class)
        ->callTableBulkAction('set_purchased', $requests)
        ->assertHasNoTableBulkActionErrors();

    expect($requests->map->refresh()->pluck('status')->all())->each->toBe(PurchaseRequestStatus::Purchased);
});
