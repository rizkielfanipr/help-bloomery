<?php

use App\Filament\Helpdesk\Resources\SalesReports\Pages\ViewSalesReport;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('esb.base_url', 'https://esb.test');
    $this->branch = Branch::factory()->create();
    $this->branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16', 'label' => 'DINE IN', 'is_active' => true]);
});

it('recalculates a shift submitted early from the complete shift window after it has ended', function () {
    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00', [
        ['sales_num' => 'A', 'sales_date_out' => '2026-09-10 09:00:00', 'payment_total' => 100_000, 'pax_total' => 10, 'revenue_total' => 100_000],
        ['sales_num' => 'B', 'sales_date_out' => '2026-09-10 13:59:00', 'payment_total' => 100_000, 'pax_total' => 10, 'revenue_total' => 100_000],
    ]);
    expect($record->isFinal())->toBeFalse()
        ->and((float) $record->basket_size)->toBe(10_000.0);

    fakeEsbSales([
        finalizeSale('BEFORE', '2026-09-10 07:59:59', 50, 900_000),
        finalizeSale('A', '2026-09-10 09:00:00', 10, 100_000),
        finalizeSale('B', '2026-09-10 13:59:00', 10, 100_000),
        finalizeSale('AFTER-SUBMIT', '2026-09-10 15:30:00', 20, 400_000),
        finalizeSale('BOUNDARY', '2026-09-10 17:00:00', 30, 700_000),
    ]);
    $this->travelTo('2026-09-10 17:30:00');

    $this->artisan('basket-size:finalize')
        ->expectsOutputToContain('Basket size dihitung: 1')
        ->assertSuccessful();

    $record->refresh()->load('employeeRecords', 'salesReport.esbTransactions');
    expect($record->isFinal())->toBeTrue()
        ->and((float) $record->revenue)->toBe(600_000.0)
        ->and($record->total_pax)->toBe(40)
        ->and((float) $record->basket_size)->toBe(15_000.0)
        ->and($record->shift_start_time)->toStartWith('08:00')
        ->and($record->shift_end_time)->toStartWith('17:00')
        ->and($record->salesReport->esbTransactions->pluck('sales_num')->sort()->values()->all())->toBe(['A', 'AFTER-SUBMIT', 'B'])
        ->and($record->employeeRecords)->toHaveCount(1)
        ->and((float) $record->employeeRecords->first()->basket_size_credit)->toBe(15_000.0);

    Http::assertSentCount(1);
    $this->artisan('basket-size:finalize')->assertSuccessful();
    Http::assertSentCount(1);
});

it('waits until the shift has ended and the grace period has passed', function () {
    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');
    fakeEsbSales([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)]);

    $this->travelTo('2026-09-10 14:00:00');
    $this->artisan('basket-size:finalize')->expectsOutputToContain('Menunggu shift berakhir: 1')->assertSuccessful();

    $this->travelTo('2026-09-10 17:14:00');
    $this->artisan('basket-size:finalize')->expectsOutputToContain('Menunggu shift berakhir: 1')->assertSuccessful();

    Http::assertNothingSent();
    expect($record->fresh()->isFinal())->toBeFalse();

    $this->travelTo('2026-09-10 17:15:00');
    $this->artisan('basket-size:finalize')->expectsOutputToContain('Basket size dihitung: 1')->assertSuccessful();

    expect($record->fresh()->isFinal())->toBeTrue();
});

it('waits for a shift that runs past midnight until it has ended the next day', function () {
    $record = submittedShift($this->branch, '2026-09-10', '22:00', '02:00');
    fakeEsbSales([
        finalizeSale('LATE', '2026-09-10 23:30:00', 10, 100_000),
        finalizeSale('EARLY', '2026-09-11 01:30:00', 10, 300_000),
    ]);

    $this->travelTo('2026-09-11 01:00:00');
    $this->artisan('basket-size:finalize')->expectsOutputToContain('Menunggu shift berakhir: 1')->assertSuccessful();
    expect($record->fresh()->isFinal())->toBeFalse();

    $this->travelTo('2026-09-11 02:20:00');
    $this->artisan('basket-size:finalize')->assertSuccessful();

    expect($record->fresh())
        ->isFinal()->toBeTrue()
        ->total_pax->toBe(20)
        ->and((float) $record->fresh()->basket_size)->toBe(20_000.0);
});

it('keeps a record provisional and retries later when ESB data cannot be loaded', function () {
    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');
    $this->travelTo('2026-09-10 18:00:00');

    $esbIsDown = true;
    Http::fake(function () use (&$esbIsDown) {
        return $esbIsDown
            ? Http::response([], 500)
            : Http::response([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)], 200, ['X-Pagination-Page-Count' => '1']);
    });

    $this->artisan('basket-size:finalize')
        ->expectsOutputToContain('Data ESB cabang belum dapat dimuat lengkap.')
        ->assertFailed();
    expect($record->fresh()->isFinal())->toBeFalse();

    $esbIsDown = false;
    $this->artisan('basket-size:finalize')->assertSuccessful();
    expect($record->fresh())->isFinal()->toBeTrue();
});

it('leaves final records alone unless forced and then uses the current master shift hours', function () {
    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');
    $record->update(['finalized_at' => now()]);
    $this->branch->salesShifts()->where('shift_number', 1)->update(['start_time' => '09:00', 'end_time' => '17:00']);
    fakeEsbSales([
        finalizeSale('EARLY', '2026-09-10 08:30:00', 40, 1_000_000),
        finalizeSale('IN-WINDOW', '2026-09-10 10:00:00', 10, 200_000),
    ]);
    $this->travelTo('2026-09-10 18:00:00');

    $this->artisan('basket-size:finalize')->assertSuccessful();
    Http::assertNothingSent();

    $this->artisan('basket-size:finalize', ['--force' => true])->assertSuccessful();

    expect($record->fresh())
        ->shift_start_time->toStartWith('09:00')
        ->total_pax->toBe(10)
        ->and((float) $record->fresh()->basket_size)->toBe(20_000.0);
});

it('only handles the requested branch and report date range and supports a dry run', function () {
    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');
    $otherBranch = Branch::factory()->create();
    $otherBranch->esbCodes()->create(['esb_branch_code' => 'BPL2', 'esb_comcode' => 'BLO3', 'label' => 'DINE IN', 'is_active' => true]);
    $other = submittedShift($otherBranch, '2026-09-10', '08:00', '17:00');
    $tooOld = submittedShift(Branch::factory()->create(), '2026-08-01', '08:00', '17:00');
    fakeEsbSales([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)]);
    $this->travelTo('2026-09-10 18:00:00');

    $this->artisan('basket-size:finalize', ['--dry-run' => true])
        ->expectsOutputToContain('Basket size akan dihitung: 2')
        ->assertSuccessful();
    Http::assertNothingSent();

    $this->artisan('basket-size:finalize', ['--branch' => $this->branch->id, '--from' => '2026-08-01'])->assertSuccessful();

    expect($record->fresh()->isFinal())->toBeTrue()
        ->and($other->fresh()->isFinal())->toBeFalse()
        ->and($tooOld->fresh()->isFinal())->toBeFalse();
});

it('skips records of branches without an ESB configuration', function () {
    $branch = Branch::factory()->create();
    $record = submittedShift($branch, '2026-09-10', '08:00', '17:00');
    $this->travelTo('2026-09-10 18:00:00');

    $this->artisan('basket-size:finalize')
        ->expectsOutputToContain('Dilewati (tanpa ESB): 1')
        ->assertSuccessful();

    expect($record->fresh()->isFinal())->toBeFalse();
});

it('marks provisional basket sizes on the sales report until they are final', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('SUPERADMIN');
    $this->actingAs($admin);

    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');

    Livewire::test(ViewSalesReport::class, ['record' => $record->salesReport])->assertSee('Sementara');

    $record->update(['finalized_at' => now()]);

    Livewire::test(ViewSalesReport::class, ['record' => $record->salesReport])->assertDontSee('Sementara');
});

it('resolves a shift window from the branch master shifts with defaults for unconfigured shifts', function () {
    $branch = Branch::factory()->create();
    expect($branch->salesShiftWindow(1))->toBe(['07:00:00', '15:00:00'])
        ->and($branch->salesShiftWindow(2))->toBe(['15:00:00', '23:00:00'])
        ->and($branch->salesShiftWindow(3))->toBe(['00:00:00', '23:59:59']);

    $branch->salesShifts()->create(['shift_number' => 1, 'name' => 'Pagi', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);

    expect($branch->fresh()->salesShiftWindow(1))->toBe(['08:00', '17:00']);
});
