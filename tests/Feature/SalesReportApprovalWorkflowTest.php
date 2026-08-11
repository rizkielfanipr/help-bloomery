<?php

use App\Enums\SalesReportStatus;
use App\Filament\Casual\Pages\SalesReportShiftPage;
use App\Filament\Helpdesk\Pages\DriverTripSettingsPage;
use App\Filament\Helpdesk\Resources\Employees\EmployeeResource;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ViewSalesReport;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportEmployee;
use App\Models\SalesReportEntry;
use App\Models\SalesReportReconciliation;
use App\Models\SalesReportShiftSubmission;
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
        'report_date' => today(),
        'submitted_at' => now(),
        'status' => SalesReportStatus::PendingSupervisor->value,
    ]);
    SalesReportShiftSubmission::create([
        'sales_report_id' => $this->report->id,
        'shift_number' => 1,
        'submitted_by' => $this->submitter->id,
        'submitted_at' => now(),
    ]);
    SalesReportEmployee::create([
        'sales_report_id' => $this->report->id,
        'shift_number' => 1,
        'employee_id' => $this->employee->id,
        'employee_code' => $this->employee->employee_code,
        'employee_name' => $this->employee->name,
        'employee_position' => $this->employee->position,
    ]);
    $this->entry = SalesReportEntry::create([
        'sales_report_id' => $this->report->id,
        'shift_number' => 1,
        'payment_method_name' => 'QRIS',
        'sales_store_amount' => 1_000_000,
    ]);
    $this->reconciliation = SalesReportReconciliation::create([
        'sales_report_id' => $this->report->id,
        'payment_method_name' => 'QRIS',
        'reported_store_amount' => 1_000_000,
        'store_amount' => 1_000_000,
        'system_amount' => 1_000_000,
        'system_fetched_at' => now(),
    ]);
});

it('only allows an active employee from the submitter branch', function () {
    $this->report->delete();
    $otherEmployee = Employee::factory()->create([
        'branch_id' => Branch::factory()->create()->id,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->submitter);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->assertSee('EMP-STORE-001')
        ->assertDontSee($otherEmployee->employee_code)
        ->set('esbFetched', true)
        ->set('rows', [[
            'label' => 'TAKEAWAY',
            'name' => 'QRIS',
            'sales_store' => '1000000',
            'notes' => '',
        ]])
        ->set('employeeIds', [$otherEmployee->id])
        ->call('requestConfirm')
        ->assertHasErrors(['employeeIds.0']);
});

it('allows selecting multiple staff for a single sales report submission', function () {
    $this->report->delete();
    $secondEmployee = Employee::factory()->create([
        'branch_id' => $this->branch->id,
        'employee_code' => 'EMP-STORE-002',
        'name' => 'Staff Kasir Kedua',
        'position' => 'Cashier',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->submitter);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->set('esbFetched', true)
        ->set('rows', [[
            'label' => 'TAKEAWAY',
            'name' => 'QRIS',
            'sales_store' => '1000000',
            'notes' => '',
        ]])
        ->set('employeeIds', [$this->employee->id, $secondEmployee->id])
        ->call('requestConfirm')
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $report = SalesReport::where('branch_id', $this->branch->id)
        ->whereDate('report_date', today())
        ->firstOrFail();

    $shiftOneEmployees = $report->employees()->where('shift_number', 1)->get();

    expect($shiftOneEmployees)->toHaveCount(2)
        ->and($shiftOneEmployees->pluck('employee_code')->sort()->values()->all())
        ->toBe(['EMP-STORE-001', 'EMP-STORE-002']);
});

it('shows the delete action on the index and deletes related report data for permitted users', function () {
    $admin = User::factory()->create([
        'is_active' => true,
        'access_all_branches' => true,
    ]);
    $admin->givePermissionTo(['view sales reports', 'delete sales reports']);
    $this->actingAs($admin);

    Livewire::test(ListSalesReports::class)
        ->assertTableActionVisible('delete', $this->report)
        ->callTableAction('delete', $this->report)
        ->assertHasNoActionErrors();

    expect(SalesReport::find($this->report->id))->toBeNull()
        ->and(SalesReportEntry::find($this->entry->id))->toBeNull();
});

it('hides the delete action on the index without delete sales reports permission', function () {
    $viewer = User::factory()->create([
        'is_active' => true,
        'access_all_branches' => true,
    ]);
    $viewer->givePermissionTo('view sales reports');
    $this->actingAs($viewer);

    Livewire::test(ListSalesReports::class)
        ->assertTableActionHidden('delete', $this->report);
});

it('keeps the app locked after submission and rejects re-save attempts', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->submitter);

    Livewire::test(SalesReportShiftPage::class, ['reportDate' => today()->toDateString()])
        ->assertSet('isSubmitted', true)
        ->assertSee('sudah dikirim')
        ->set('rows.0.sales_store', '500000')
        ->call('save');

    expect((float) $this->entry->refresh()->sales_store_amount)->toBe(1000000.0);
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

    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->set("settlementRows.{$this->reconciliation->id}.settlement", '993000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    $this->report->refresh();
    $this->reconciliation->refresh();

    expect($this->report->status)->toBe(SalesReportStatus::Completed)
        ->and($this->report->finance_reviewed_by)->toBe($finance->id)
        ->and((float) $this->reconciliation->mdr_amount)->toBe(7000.0)
        ->and((float) $this->reconciliation->mdr_percentage)->toBe(0.7)
        ->and((float) $this->reconciliation->expected_settlement_amount)->toBe(993000.0)
        ->and((float) $this->reconciliation->settlement_difference)->toBe(0.0)
        ->and($this->reconciliation->reconciliation_status)->toBe('matched')
        ->and($this->report->approvals()->count())->toBe(2);
});

it('requires supervisor notes while corrected store and system sales still differ', function () {
    $this->reconciliation->update(['store_amount' => 990_000]);
    $supervisor = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $this->actingAs($supervisor);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->call('approveSupervisor')
        ->assertHasErrors(["supervisorRows.{$this->reconciliation->id}.notes"]);

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::PendingSupervisor);
});

it('returns a finance rejection to the supervisor with an audit trail', function () {
    $this->report->update(['status' => SalesReportStatus::PendingFinance->value]);
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->set('rejectionReason', 'Settlement bank belum sesuai.')
        ->call('rejectFinance')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::PendingSupervisor)
        ->and($this->report->approvals()->where('action', 'rejected')->exists())->toBeTrue();
});

it('shows an informative empty state for a label with no reconciliation data', function () {
    // beforeEach only creates a TAKEAWAY entry/reconciliation, so DINE IN
    // has zero rows for this report — it should still render with a
    // message instead of disappearing entirely.
    $supervisor = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $this->actingAs($supervisor);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->assertSee('DINE IN')
        ->assertSee('Tidak ada data DINE IN untuk laporan ini.')
        ->assertSee('TAKEAWAY')
        ->assertDontSee('Tidak ada data TAKEAWAY untuk laporan ini.');
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

it('preserves first input while the supervisor corrects the working values', function () {
    $this->reconciliation->update([
        'reported_store_amount' => 900_000,
        'store_amount' => 900_000,
    ]);

    $supervisor = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
    ]);
    $supervisor->assignRole('SUPERVISOR_STORE');
    $this->actingAs($supervisor);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->set("supervisorRows.{$this->reconciliation->id}.store_amount", '1000000')
        ->set("supervisorRows.{$this->reconciliation->id}.notes", 'Shortage reconciled by supervisor.')
        ->call('approveSupervisor')
        ->assertHasNoErrors();

    $this->report->refresh();
    $this->reconciliation->refresh();

    expect($this->report->status)->toBe(SalesReportStatus::PendingFinance)
        ->and((float) $this->reconciliation->reported_store_amount)->toBe(900000.0)
        ->and((float) $this->reconciliation->store_amount)->toBe(1000000.0)
        ->and($this->reconciliation->supervisor_notes)->toBe('Shortage reconciled by supervisor.');
});

it('calculates mdr percentage automatically from settlement', function () {
    $this->report->update(['status' => SalesReportStatus::PendingFinance->value]);
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->set("settlementRows.{$this->reconciliation->id}.settlement", '900000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::Completed)
        ->and((float) $this->reconciliation->refresh()->mdr_amount)->toBe(100000.0)
        ->and((float) $this->reconciliation->mdr_percentage)->toBe(10.0);
});

it('shows the workflow status in the finance sales report list', function () {
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ListSalesReports::class)
        ->assertSee($this->branch->name)
        ->assertSee('Supervisor Review');
});

it('uses concise English labels throughout the sales report workflow', function () {
    expect(SalesReportStatus::Draft->getLabel())->toBe('Draft')
        ->and(SalesReportStatus::PendingSupervisor->getLabel())->toBe('Supervisor Review')
        ->and(SalesReportStatus::PendingFinance->getLabel())->toBe('Finance Review')
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

    $page->set("settlementRows.{$this->reconciliation->id}.settlement", '995000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::Completed)
        ->and($this->report->supervisor_reviewed_by)->toBe($superadmin->id)
        ->and($this->report->finance_reviewed_by)->toBe($superadmin->id)
        ->and($this->reconciliation->refresh()->reconciliation_status)->toBe('matched');
});

it('allows a superadmin to approve a report they submitted for testing', function () {
    $superadmin = User::factory()->create([
        'branch_id' => $this->branch->id,
        'is_active' => true,
        'access_all_branches' => true,
    ]);
    $superadmin->assignRole('SUPERADMIN');
    $this->actingAs($superadmin);

    $page = Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->assertSee('Set as Finance Review')
        ->call('approveSupervisor')
        ->assertHasNoErrors()
        ->assertSee('Set as Completed');

    $page->set("settlementRows.{$this->reconciliation->id}.settlement", '995000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->report->refresh()->status)->toBe(SalesReportStatus::Completed);
});

it('allows finance to input settlement for cash payment methods', function () {
    $this->entry->update(['payment_method_name' => 'Cash']);
    $this->reconciliation->update(['payment_method_name' => 'Cash']);
    $this->report->update(['status' => SalesReportStatus::PendingFinance->value]);
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    Livewire::test(ViewSalesReport::class, ['record' => $this->report])
        ->assertDontSee('N/A')
        ->set("settlementRows.{$this->reconciliation->id}.settlement", '1000000')
        ->call('approveFinance')
        ->assertHasNoErrors();

    expect($this->reconciliation->refresh()->settlement_amount)->not->toBeNull()
        ->and($this->reconciliation->reconciliation_status)->toBe('matched');
});

it('locks shift two until shift one has been submitted', function () {
    $this->report->delete();
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $this->actingAs($this->submitter);

    Livewire::test(SalesReportShiftPage::class, [
        'reportDate' => today()->toDateString(),
        'shiftNumber' => 2,
    ])->assertRedirect();

    expect(SalesReportShiftSubmission::query()
        ->whereHas('salesReport', fn ($query) => $query
            ->where('branch_id', $this->branch->id)
            ->whereDate('report_date', today()))
        ->where('shift_number', 2)
        ->exists())->toBeFalse();
});
