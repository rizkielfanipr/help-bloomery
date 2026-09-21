<?php

use App\Actions\CalculateBasketSizeAction;
use App\Filament\Helpdesk\Pages\BasketSizePage;
use App\Filament\Helpdesk\Resources\Branches\Pages\EditBranch;
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

it('calculates shift basket size and gives every staff the full basket size credit', function () {
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
        ->and($record->employeeRecords->every(fn ($row): bool => (float) $row->basket_size_credit === 40000.0))->toBeTrue();
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
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
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

it('ranks employees by average credit instead of total credit', function () {
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    $branch = Branch::factory()->create();
    $report = SalesReport::factory()->create(['branch_id' => $branch->id, 'report_date' => '2026-09-01']);
    $higherAverage = Employee::factory()->create(['branch_id' => $branch->id, 'name' => 'Higher Average']);
    $higherTotal = Employee::factory()->create(['branch_id' => $branch->id, 'name' => 'Higher Total']);

    foreach ([1 => [$higherAverage, 60000], 2 => [$higherTotal, 40000], 3 => [$higherTotal, 40000]] as $shiftNumber => [$employee, $credit]) {
        $record = $report->basketSizeRecords()->create([
            'branch_id' => $branch->id,
            'report_date' => '2026-09-01',
            'shift_number' => $shiftNumber,
            'shift_name' => 'Shift '.$shiftNumber,
            'shift_start_time' => '07:00',
            'shift_end_time' => '15:00',
            'revenue' => 1000000,
            'total_pax' => 20,
            'basket_size' => $credit,
            'staff_count' => 1,
        ]);
        $record->employeeRecords()->create([
            'sales_report_id' => $report->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'basket_size_credit' => $credit,
        ]);
    }

    $ranking = Livewire::test(BasketSizePage::class)
        ->set('dateFrom', '2026-09-01')
        ->set('dateTo', '2026-09-01')
        ->instance()
        ->ranking();

    expect($ranking->pluck('employee_id')->all())->toBe([$higherAverage->id, $higherTotal->id])
        ->and((float) $ranking[0]->average_credit)->toBe(60000.0)
        ->and((float) $ranking[1]->total_credit)->toBe(80000.0);
});

it('keeps recorded basket sizes unchanged when the branch shift hours are edited and applies the new hours to later calculations', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $branch = Branch::factory()->create();
    $shift = $branch->salesShifts()->create([
        'shift_number' => 1,
        'name' => 'Shift 1',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'is_active' => true,
    ]);
    $makeReport = function (string $date) use ($branch): SalesReport {
        $report = SalesReport::factory()->create(['branch_id' => $branch->id, 'report_date' => $date]);
        $report->esbTransactions()->create([
            'shift_number' => 1,
            'sales_num' => 'SALE-'.$date,
            'sales_date_out' => "{$date} 10:00:00",
            'payment_total' => 5_000_000,
            'pax_total' => 100,
            'revenue_total' => 5_000_000,
        ]);

        return $report;
    };

    $before = app(CalculateBasketSizeAction::class)->execute($makeReport('2026-09-01'), 1);
    expect($before->shift_start_time)->toStartWith('07:00')
        ->and((float) $before->basket_size)->toBe(50000.0);

    $edit = Livewire::test(EditBranch::class, ['record' => $branch->id]);
    $state = collect($edit->get('data.salesShifts'))
        ->map(fn (array $item): array => [...$item, 'start_time' => '09:00', 'end_time' => '17:00'])
        ->all();
    $edit->fillForm(['salesShifts' => $state])->call('save')->assertHasNoFormErrors();

    expect($branch->salesShifts()->pluck('id')->all())->toBe([$shift->id])
        ->and($shift->fresh()->start_time)->toStartWith('09:00');

    $after = $before->fresh();
    expect($after->shift_start_time)->toStartWith('07:00')
        ->and($after->shift_end_time)->toStartWith('15:00')
        ->and($after->branch_sales_shift_id)->toBe($shift->id)
        ->and((float) $after->basket_size)->toBe(50000.0)
        ->and($after->calculated_at->equalTo($before->calculated_at))->toBeTrue();

    $later = app(CalculateBasketSizeAction::class)->execute($makeReport('2026-09-02'), 1);
    expect($later->shift_start_time)->toStartWith('09:00')
        ->and($later->shift_end_time)->toStartWith('17:00');
});

it('keeps the recorded shift name and hours when a branch shift is deleted', function () {
    $branch = Branch::factory()->create();
    $shift = $branch->salesShifts()->create([
        'shift_number' => 1,
        'name' => 'Opening',
        'start_time' => '07:00',
        'end_time' => '15:00',
        'is_active' => true,
    ]);
    $report = SalesReport::factory()->create(['branch_id' => $branch->id, 'report_date' => '2026-09-01']);
    $record = app(CalculateBasketSizeAction::class)->execute($report, 1);

    $shift->delete();

    expect($record->fresh())
        ->branch_sales_shift_id->toBeNull()
        ->shift_name->toBe('Opening')
        ->shift_start_time->toStartWith('07:00')
        ->shift_end_time->toStartWith('15:00');
});
