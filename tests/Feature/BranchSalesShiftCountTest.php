<?php

use App\Enums\SalesReportStatus;
use App\Filament\Casual\Pages\SalesReportPage;
use App\Filament\Casual\Pages\SalesReportShiftPage;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\SalesReportShiftSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('reports shift availability from the branch model helpers', function () {
    $oneShift = Branch::factory()->create(['sales_shift_count' => 1]);
    $twoShift = Branch::factory()->create(['sales_shift_count' => 2]);

    expect($oneShift->hasSalesShift(1))->toBeTrue()
        ->and($oneShift->hasSalesShift(2))->toBeFalse()
        ->and($oneShift->salesShiftNumbers())->toBe([1])
        ->and($twoShift->hasSalesShift(1))->toBeTrue()
        ->and($twoShift->hasSalesShift(2))->toBeTrue()
        ->and($twoShift->salesShiftNumbers())->toBe([1, 2]);
});

it('only shows the shift tiles a branch actually has on the mobile sales report page', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $branch = Branch::factory()->create(['sales_shift_count' => 1]);
    $staff = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $staff->assignRole('CASUAL_STAFF');
    $this->actingAs($staff);

    Livewire::test(SalesReportPage::class)
        ->assertSee('Shift 1')
        ->assertDontSee('Shift 2');
});

it('redirects away from an out-of-range shift for a single-shift branch', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $branch = Branch::factory()->create(['sales_shift_count' => 1]);
    $staff = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $staff->assignRole('CASUAL_STAFF');
    $this->actingAs($staff);

    Livewire::test(SalesReportShiftPage::class, [
        'reportDate' => today()->toDateString(),
        'shiftNumber' => 2,
    ])->assertRedirect(route('filament.casual.pages.sales-report-page', [
        'reportDate' => today()->toDateString(),
    ]));
});

it('still locks shift 2 behind shift 1 submission for a two-shift branch', function () {
    Filament::setCurrentPanel(Filament::getPanel('casual'));
    $branch = Branch::factory()->create(['sales_shift_count' => 2]);
    $staff = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $staff->assignRole('CASUAL_STAFF');
    Employee::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
    $this->actingAs($staff);

    Livewire::test(SalesReportShiftPage::class, [
        'reportDate' => today()->toDateString(),
        'shiftNumber' => 2,
    ])->assertRedirect(route('filament.casual.pages.sales-report-page', [
        'reportDate' => today()->toDateString(),
    ]));

    $report = SalesReport::create([
        'branch_id' => $branch->id,
        'report_date' => today(),
        'status' => SalesReportStatus::Draft->value,
    ]);

    SalesReportShiftSubmission::create([
        'sales_report_id' => $report->id,
        'shift_number' => 1,
        'submitted_by' => $staff->id,
        'submitted_at' => now(),
    ]);

    Livewire::test(SalesReportShiftPage::class, [
        'reportDate' => today()->toDateString(),
        'shiftNumber' => 2,
    ])->assertSuccessful();
});
