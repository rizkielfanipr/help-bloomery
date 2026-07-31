<?php

use App\Enums\SalesReportStatus;
use App\Filament\Casual\Pages\SalesReportShiftPage;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ViewSalesReport;
use App\Filament\Helpdesk\Pages\DriverTripSettingsPage;
use App\Filament\Helpdesk\Resources\Employees\EmployeeResource;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->branch = Branch::factory()->create();
    $this->submitter = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $this->submitter->assignRole('CASUAL_STAFF');
    $this->employee = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'employee_code' => 'EMP-STORE-001',
        'name' => 'Staff Kasir',
        'position' => 'Cashier',
    ]);

    $this->report = SalesReport::create([
        'branch_id' => $this->branch->id,
        'submitted_by' => $this->submitter->id,
        'employee_id' => $this->employee->id,
        'employee_code' => $this->employee->employee_code,
        'employee_name' => $this->employee->name,
        'employee_position' => $this->employee->position,
        'report_date' => today(),
        'submitted_at' => now(),
        'status' => SalesReportStatus::PendingSupervisor->value,
    ]);
    $this->entry = SalesReportEntry::create([
        'sales_report_id' => $this->report->id,
        'payment_method_name' => 'QRIS',
        'sales_system_amount' => 1_000_000,
        'sales_store_amount' => 1_000_000,
    ]);
});

it('only allows an active employee from the submitter branch', function () {
    $this->report->update(['status' => SalesReportStatus::RejectedBySupervisor->value]);
    $otherEmployee = Employee::factory()->create([
        'branch_id' => Branch::factory()->create()->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->submitter);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->assertSee('EMP-STORE-001')
        ->assertDontSee($otherEmployee->employee_code)
        ->set('employeeId', $otherEmployee->id)
        ->call('requestConfirm')
        ->assertHasErrors(['employeeId']);
});

it('hides system sales and requires notes for each differing payment method', function () {
    $this->report->update(['status' => SalesReportStatus::RejectedBySupervisor->value]);
    $this->entry->update(['sales_store_amount' => 900_000]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->submitter);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->assertDontSee('Sales System')
        ->assertDontSee('1.000.000')
        ->call('requestConfirm')
        ->assertSet('showDiscrepancies', true)
        ->assertSee('Difference detected')
        ->assertHasErrors(['rows.0.notes'])
        ->set('rows.0.notes', 'Cash count was below the recorded transaction total.')
        ->call('requestConfirm')
        ->assertHasNoErrors()
        ->assertSet('showConfirm', true);
});

it('moves a report from supervisor approval through finance reconciliation', function () {
    $supervisor = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $supervisor->assignRole('SUPERVISOR_STORE');

    $this->actingAs($supervisor);
    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->set('reviewNote', 'Data toko sudah diperiksa.')
        ->call('approveSupervisor')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::PendingFinance)
        ->and($this->report->supervisor_reviewed_by)->toBe($supervisor->id);

    $finance = User::factory()->create(['is_active' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->set("settlementRows.{$this->entry->id}.settlement", '993000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    $this->report->refresh();
    $this->entry->refresh();

    expect($this->report->status)->toBe(SalesReportStatus::Completed)
        ->and($this->report->finance_reviewed_by)->toBe($finance->id)
        ->and((float) $this->entry->mdr_amount)->toBe(7000.0)
        ->and((float) $this->entry->mdr_percentage)->toBe(0.7)
        ->and((float) $this->entry->expected_settlement_amount)->toBe(993000.0)
        ->and((float) $this->entry->settlement_difference)->toBe(0.0)
        ->and($this->entry->reconciliation_status)->toBe('matched')
        ->and($this->report->approvals()->count())->toBe(2);
});

it('requires a supervisor note when store and system sales differ', function () {
    $this->entry->update(['sales_store_amount' => 990_000]);
    $supervisor = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $this->actingAs($supervisor);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->call('approveSupervisor')
        ->assertHasErrors(['reviewNote']);

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::PendingSupervisor);
});

it('returns a finance rejection to the submitter with an audit trail', function () {
    $this->report->update(['status' => SalesReportStatus::PendingFinance->value]);
    $finance = User::factory()->create(['is_active' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->set('rejectionReason', 'Settlement bank belum sesuai.')
        ->call('rejectFinance')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::RejectedByFinance)
        ->and($this->report->rejection_reason)->toBe('Settlement bank belum sesuai.')
        ->and($this->report->approvals()->where('action', 'rejected')->exists())->toBeTrue();
});

it('prevents supervisors from other branches from reviewing the report', function () {
    $supervisor = User::factory()->create([
        'branch_id' => Branch::factory()->create()->id,
        'is_active' => true,
    ]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $this->actingAs($supervisor);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->assertForbidden();
});

it('resubmits a rejected report and clears the previous finance reconciliation', function () {
    $this->report->update([
        'status' => SalesReportStatus::RejectedByFinance->value,
        'rejection_reason' => 'Settlement perlu diperbaiki.',
    ]);
    $this->entry->update([
        'settlement_amount' => 900_000,
        'mdr_percentage' => 1,
        'mdr_amount' => 10_000,
        'expected_settlement_amount' => 990_000,
        'settlement_difference' => -90_000,
        'reconciliation_status' => 'under',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->submitter);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->set('rows.0.sales_store', '1000000')
        ->call('save')
        ->assertHasNoErrors();

    $this->report->refresh();
    $this->entry->refresh();

    expect($this->report->status)->toBe(SalesReportStatus::PendingSupervisor)
        ->and($this->report->revision_number)->toBe(1)
        ->and($this->report->rejection_reason)->toBeNull()
        ->and($this->entry->settlement_amount)->toBeNull()
        ->and($this->entry->reconciliation_status)->toBeNull()
        ->and($this->report->approvals()->where('action', 'resubmitted')->exists())->toBeTrue();
});

it('calculates mdr percentage automatically from settlement', function () {
    $this->report->update(['status' => SalesReportStatus::PendingFinance->value]);
    $finance = User::factory()->create(['is_active' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->set("settlementRows.{$this->entry->id}.settlement", '900000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::Completed)
        ->and((float) $this->entry->refresh()->mdr_amount)->toBe(100000.0)
        ->and((float) $this->entry->mdr_percentage)->toBe(10.0);
});

it('shows the workflow status in the finance sales report list', function () {
    $finance = User::factory()->create(['is_active' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ListSalesReports::class)
        ->assertSee($this->branch->name)
        ->assertSee('Supervisor Review');
});

it('uses concise English labels throughout the sales report workflow', function () {
    expect(SalesReportStatus::Draft->getLabel())->toBe('Draft')
        ->and(SalesReportStatus::PendingSupervisor->getLabel())->toBe('Supervisor Review')
        ->and(SalesReportStatus::RejectedBySupervisor->getLabel())->toBe('Rejected by Supervisor')
        ->and(SalesReportStatus::PendingFinance->getLabel())->toBe('Finance Review')
        ->and(SalesReportStatus::RejectedByFinance->getLabel())->toBe('Rejected by Finance')
        ->and(SalesReportStatus::Completed->getLabel())->toBe('Completed');
});

it('grants supervisors full employee access without driver access', function () {
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->assignRole('SUPERVISOR_STORE');

    expect($supervisor->can('view employees'))->toBeTrue()
        ->and($supervisor->can('create employees'))->toBeTrue()
        ->and($supervisor->can('edit employees'))->toBeTrue()
        ->and($supervisor->can('delete employees'))->toBeTrue()
        ->and($supervisor->can('view trips'))->toBeFalse()
        ->and($supervisor->can('create trips'))->toBeFalse()
        ->and($supervisor->can('edit trips'))->toBeFalse()
        ->and($supervisor->can('delete trips'))->toBeFalse()
        ->and($supervisor->can('view trip routes'))->toBeFalse()
        ->and($supervisor->can('view vehicles'))->toBeFalse()
        ->and($supervisor->can('view tile driver'))->toBeFalse();

    $this->actingAs($supervisor);
    expect(DriverTripSettingsPage::canAccess())->toBeFalse();
});

it('limits employee branches and records to branches accessible by the supervisor', function () {
    $monitoredBranch = Branch::factory()->create(['name' => 'Monitored Branch']);
    $hiddenBranch = Branch::factory()->create(['name' => 'Hidden Branch']);
    $supervisor = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $supervisor->accessibleBranches()->attach($monitoredBranch->id);
    $hiddenEmployee = Employee::factory()->create(['branch_id' => $hiddenBranch->id]);

    $this->actingAs($supervisor);

    expect(EmployeeResource::branchOptions())
        ->toHaveKeys([$this->branch->id, $monitoredBranch->id])
        ->not->toHaveKey($hiddenBranch->id)
        ->and(EmployeeResource::getEloquentQuery()->whereKey($hiddenEmployee)->exists())->toBeFalse()
        ->and(EmployeeResource::canEdit($hiddenEmployee))->toBeFalse()
        ->and(EmployeeResource::canDelete($hiddenEmployee))->toBeFalse();
});

it('allows a superadmin to test both supervisor and finance approval stages', function () {
    $superadmin = User::factory()->create([
        'is_active' => true,
        'access_all_branches' => true,
    ]);
    $superadmin->assignRole('SUPERADMIN');
    $this->actingAs($superadmin);

    $page = Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->call('approveSupervisor')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::PendingFinance);

    $page->set("settlementRows.{$this->entry->id}.settlement", '995000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::Completed)
        ->and($this->report->supervisor_reviewed_by)->toBe($superadmin->id)
        ->and($this->report->finance_reviewed_by)->toBe($superadmin->id)
        ->and($this->entry->refresh()->reconciliation_status)->toBe('matched');
});

it('allows a superadmin to approve a report they submitted for testing', function () {
    $superadmin = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
        'access_all_branches' => true,
    ]);
    $superadmin->assignRole('SUPERADMIN');
    $this->report->update(['submitted_by' => $superadmin->id]);
    $this->actingAs($superadmin);

    $page = Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->assertSee('Set as Finance Review')
        ->call('approveSupervisor')
        ->assertHasNoErrors()
        ->assertSee('Set as Completed');

    $page->set("settlementRows.{$this->entry->id}.settlement", '995000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::Completed);
});

it('allows finance to input settlement for cash payment methods', function () {
    $this->entry->update(['payment_method_name' => 'Cash']);
    $this->report->update(['status' => SalesReportStatus::PendingFinance->value]);
    $finance = User::factory()->create(['is_active' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->assertDontSee('N/A')
        ->set("settlementRows.{$this->entry->id}.settlement", '1000000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->entry->refresh()->settlement_amount)->not->toBeNull()
        ->and($this->entry->reconciliation_status)->toBe('matched');
});

it('locks shift two until shift one has been submitted', function () {
    $this->report->delete();
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->submitter);

    Livewire::test(SalesReportShiftPage::class, [
        'reportDate' => today()->toDateString(),
        'shiftNumber' => 2,
    ])->assertRedirect();

    expect(SalesReport::query()
        ->where('branch_id', $this->branch->id)
        ->whereDate('report_date', today())
        ->where('shift_number', 2)
        ->exists())->toBeFalse();
});
