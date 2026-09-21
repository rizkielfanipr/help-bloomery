<?php

use App\Enums\ServiceRequestStatus;
use App\Filament\Casual\Resources\ServiceRequests\Pages\ListServiceRequests as CasualListServiceRequests;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\ListServiceRequests as HelpdeskListServiceRequests;
use App\Models\Branch;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->technician = User::factory()->create(['name' => 'Budi Santoso', 'username' => 'BLOTECH1', 'is_active' => true]);
    $this->technician->assignRole('TECHNICIAN');
    $this->technician->syncBranchAccess([$this->branch->id], $this->branch->id);
    $this->request = ServiceRequest::factory()->create([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'status' => ServiceRequestStatus::Submitted,
    ]);
});

it('shows the technician username instead of the name in the technician app', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->technician);

    $html = Livewire::test(CasualListServiceRequests::class)
        ->assertSee('BLOTECH1')
        ->html();

    // The header greets the signed-in user by name; the job card must not repeat it.
    expect(substr_count($html, 'Budi Santoso'))->toBe(1);
});

it('shows the technician username in the back office table and searches by it', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($admin);
    $other = ServiceRequest::factory()->create(['branch_id' => $this->branch->id, 'technician_id' => null]);

    Livewire::test(HelpdeskListServiceRequests::class)
        ->assertTableColumnStateSet('technician.username', 'BLOTECH1', $this->request)
        ->searchTable('BLOTECH1')
        ->assertCanSeeTableRecords([$this->request])
        ->assertCanNotSeeTableRecords([$other]);
});

it('falls back to the name for technicians without a username', function () {
    $this->technician->update(['username' => null]);

    expect($this->technician->fresh()->display_username)->toBe('Budi Santoso');
});
