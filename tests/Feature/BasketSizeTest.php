<?php

use App\Actions\CalculateBasketSizeAction;
use App\Filament\Helpdesk\Pages\BasketSizePage;
use App\Models\BasketSizeEmployeeRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\SalesReport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
});

it('calculates shift basket size and divides its credit between staff', function () {
    $branch = Branch::factory()->create(['sales_shift_count' => 2]);
    $shift = $branch->salesShifts()->create([
        'shift_number' => 1,
        'name' => 'Opening',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'is_active' => true,
    ]);
    $report = SalesReport::factory()->create([
        'branch_id' => $branch->id,
        'report_date' => '2026-09-01',
    ]);
    $employees = Employee::factory()->count(2)->create(['branch_id' => $branch->id]);
    foreach ($employees as $employee) {
        $report->employees()->create([
            'shift_number' => 1,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'employee_position' => $employee->position,
        ]);
    }
    $report->esbTransactions()->create([
        'shift_number' => 1,
        'source_branch_code' => 'BPL',
        'source_comcode' => 'BLSS',
        'sales_num' => 'SALE-001',
        'sales_date_out' => '2026-09-01 10:00:00',
        'payment_total' => 10000000,
        'pax_total' => 250,
        'revenue_total' => 10000000,
    ]);

    $record = app(CalculateBasketSizeAction::class)->execute($report, 1);

    expect($record->branch_sales_shift_id)->toBe($shift->id)
        ->and((float) $record->revenue)->toBe(10000000.0)
        ->and($record->total_pax)->toBe(250)
        ->and((float) $record->basket_size)->toBe(40000.0)
        ->and($record->staff_count)->toBe(2)
        ->and($record->employeeRecords)->toHaveCount(2)
        ->and($record->employeeRecords->every(fn ($row): bool => (float) $row->basket_size_credit === 20000.0))->toBeTrue();
});

it('does not divide by zero when a shift has no pax', function () {
    $branch = Branch::factory()->create();
    $report = SalesReport::factory()->create(['branch_id' => $branch->id]);
    $employee = Employee::factory()->create(['branch_id' => $branch->id]);
    $report->employees()->create([
        'shift_number' => 1,
        'employee_id' => $employee->id,
        'employee_name' => $employee->name,
    ]);

    $record = app(CalculateBasketSizeAction::class)->execute($report, 1);

    expect($record->basket_size)->toBeNull()
        ->and($record->employeeRecords->first()->basket_size_credit)->toBeNull();
});

it('shows finance the ranking and employee sales report history', function () {
    $finance = User::factory()->create(['is_active' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    $branch = Branch::factory()->create(['name' => 'Bloomery Kemang']);
    $report = SalesReport::factory()->create(['branch_id' => $branch->id, 'report_date' => '2026-09-01']);
    $employee = Employee::factory()->create(['branch_id' => $branch->id, 'name' => 'Budi Basket']);
    $basket = $report->basketSizeRecords()->create([
        'branch_id' => $branch->id,
        'report_date' => '2026-09-01',
        'shift_number' => 1,
        'shift_name' => 'Opening',
        'shift_start_time' => '07:00',
        'shift_end_time' => '15:00',
        'revenue' => 1000000,
        'total_pax' => 20,
        'basket_size' => 50000,
        'staff_count' => 1,
    ]);
    $basket->employeeRecords()->create([
        'sales_report_id' => $report->id,
        'employee_id' => $employee->id,
        'employee_name' => $employee->name,
        'basket_size_credit' => 50000,
    ]);

    $page = Livewire::test(BasketSizePage::class)
        ->set('dateFrom', '2026-09-01')
        ->set('dateTo', '2026-09-01');

    expect($basket->fresh()->report_date->toDateString())->toBe('2026-09-01')
        ->and(BasketSizeEmployeeRecord::query()->count())->toBe(1)
        ->and($page->instance()->dateFrom)->toBe('2026-09-01')
        ->and($page->instance()->dateTo)->toBe('2026-09-01')
        ->and($page->instance()->ranking())->toHaveCount(1);

    $page
        ->assertSee('Budi Basket')
        ->set('employee', $employee->id)
        ->assertSee('Bloomery Kemang')
        ->assertSee('Sales Report');
});
