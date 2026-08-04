<?php

use App\Enums\SalesReportStatus;
use App\Filament\Casual\Pages\SalesReportPage;
use App\Filament\Casual\Pages\SalesReportShiftPage;
use App\Filament\Helpdesk\Resources\Branches\Pages\CreateBranch;
use App\Filament\Helpdesk\Resources\Branches\Pages\EditBranch;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('SUPERADMIN');
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

it('creates a branch with a single sales shift without requiring shift 2 times', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($this->admin);

    Livewire::test(CreateBranch::class)
        ->fillForm([
            'name' => 'Branch Satu Shift',
            'sales_shift_count' => 1,
            'sales_shift_1_start' => '08:00',
            'sales_shift_1_end' => '17:00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $branch = Branch::query()->where('name', 'Branch Satu Shift')->firstOrFail();
    expect($branch->sales_shift_count)->toBe(1);
});

it('requires shift 2 times on the branch form when two shifts are selected', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($this->admin);

    Livewire::test(CreateBranch::class)
        ->fillForm([
            'name' => 'Branch Dua Shift',
            'sales_shift_count' => 2,
            'sales_shift_1_start' => '08:00',
            'sales_shift_1_end' => '15:00',
            'sales_shift_2_start' => null,
            'sales_shift_2_end' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['sales_shift_2_start', 'sales_shift_2_end']);
});

it('lets an existing branch be edited down to a single shift', function () {
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->actingAs($this->admin);
    $branch = Branch::factory()->create(['sales_shift_count' => 2]);

    Livewire::test(EditBranch::class, ['record' => $branch->id])
        ->fillForm(['sales_shift_count' => 1])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($branch->fresh()->sales_shift_count)->toBe(1);
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

    SalesReport::create([
        'branch_id' => $branch->id,
        'submitted_by' => $staff->id,
        'report_date' => today(),
        'shift_number' => 1,
        'submitted_at' => now(),
        'status' => SalesReportStatus::PendingSupervisor->value,
    ]);

    Livewire::test(SalesReportShiftPage::class, [
        'reportDate' => today()->toDateString(),
        'shiftNumber' => 2,
    ])->assertSuccessful();
});
