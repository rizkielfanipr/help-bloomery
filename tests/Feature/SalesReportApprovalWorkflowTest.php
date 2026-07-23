<?php

use App\Enums\SalesReportStatus;
use App\Filament\Casual\Pages\SalesReportShiftPage;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ListSalesReports;
use App\Filament\Helpdesk\Resources\SalesReports\Pages\ViewSalesReport;
use App\Models\Branch;
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

    $this->report = SalesReport::create([
        'branch_id' => $this->branch->id,
        'submitted_by' => $this->submitter->id,
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
        ->assertSee('Menunggu Approval SPV');
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
        ->assertSee('Approve SPV')
        ->call('approveSupervisor')
        ->assertHasNoErrors()
        ->assertSee('Approve & Selesaikan');

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
