<?php

use App\Enums\ServiceRequestStatus;
use App\Filament\Casual\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\CreateServiceRequest;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('SUPERADMIN');
    $this->admin->syncBranchAccess([$this->branch->id], $this->branch->id);
    $this->technician = User::factory()->create(['is_active' => true]);
    $this->technician->assignRole('TECHNICIAN');
    $this->technician->syncBranchAccess([$this->branch->id], $this->branch->id);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($this->admin);
});

it('puts a back office request in the assigned technician logbook immediately', function (?string $date) {
    Livewire::test(CreateServiceRequest::class)
        ->assertFormSet(['branch_id' => $this->branch->id])
        ->fillForm(['scheduled_date' => $date, 'requestor_notes' => 'Mesin perlu diperiksa'])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = ServiceRequest::sole();
    expect($request->branch_id)->toBe($this->branch->id)
        ->and($request->technician_id)->toBe($this->technician->id)
        ->and($request->assigned_at)->not->toBeNull()
        ->and($request->status)->toBe(ServiceRequestStatus::Submitted)
        ->and($request->scheduled_date?->toDateString())->toBe($date);
    expect($this->technician->notifications()->count())->toBe(1);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->technician);
    Livewire::test(ListServiceRequests::class)->assertSee($request->code);
})->with(['without schedule' => null, 'future schedule' => '2030-01-10']);

it('keeps requests without an available technician visible as unassigned jobs in their branch', function () {
    $this->technician->syncRoles([]);
    Livewire::test(CreateServiceRequest::class)
        ->fillForm(['scheduled_date' => null, 'requestor_notes' => 'Pekerjaan baru'])
        ->call('create')
        ->assertHasNoFormErrors();
    $request = ServiceRequest::sole();
    expect($request->branch_id)->toBe($this->branch->id)
        ->and($request->technician_id)->toBeNull();

    $this->technician->assignRole('TECHNICIAN');
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->technician);
    Livewire::test(ListServiceRequests::class)->assertSee($request->code);

    $otherBranch = Branch::factory()->create();
    $otherTechnician = User::factory()->create(['is_active' => true]);
    $otherTechnician->assignRole('TECHNICIAN');
    $otherTechnician->syncBranchAccess([$otherBranch->id], $otherBranch->id);
    $this->actingAs($otherTechnician);
    Livewire::test(ListServiceRequests::class)->assertDontSee($request->code);
});

it('rejects creating a request for a branch outside the requester access', function () {
    $otherBranch = Branch::factory()->create();
    $this->admin->syncRoles([]);
    $this->admin->givePermissionTo(['access backoffice', 'create service requests', 'view service requests']);

    Livewire::test(CreateServiceRequest::class)
        ->fillForm(['branch_id' => $otherBranch->id, 'scheduled_date' => null])
        ->call('create')
        ->assertHasFormErrors(['branch_id']);

    expect(ServiceRequest::count())->toBe(0);
});

it('backfills the branch of legacy requests so technicians can see and work them', function () {
    $this->technician->update(['branch_id' => null]);
    $requester = User::factory()->create(['branch_id' => $this->branch->id]);
    $asset = Asset::factory()->create(['branch_id' => $this->branch->id]);

    $fromAsset = ServiceRequest::factory()->create(['branch_id' => null, 'asset_id' => $asset->id, 'scheduled_by' => $this->admin->id, 'technician_id' => null, 'status' => ServiceRequestStatus::Submitted]);
    $fromRequester = ServiceRequest::factory()->create(['branch_id' => null, 'asset_id' => null, 'scheduled_by' => $requester->id, 'technician_id' => null, 'status' => ServiceRequestStatus::Submitted]);
    $orphan = ServiceRequest::factory()->create(['branch_id' => null, 'asset_id' => null, 'scheduled_by' => User::factory()->create(['branch_id' => null])->id, 'technician_id' => null, 'status' => ServiceRequestStatus::Submitted]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->technician);
    Livewire::test(ListServiceRequests::class)
        ->assertDontSee($fromAsset->code)
        ->assertDontSee($fromRequester->code);

    (require database_path('migrations/2026_09_21_082248_backfill_branch_on_service_requests.php'))->up();

    expect($fromAsset->fresh()->branch_id)->toBe($this->branch->id)
        ->and($fromRequester->fresh()->branch_id)->toBe($this->branch->id)
        ->and($orphan->fresh()->branch_id)->toBeNull();
    Livewire::test(ListServiceRequests::class)
        ->assertSee($fromAsset->code)
        ->assertSee($fromRequester->code)
        ->assertDontSee($orphan->code);
});
