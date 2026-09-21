<?php

use App\Filament\Helpdesk\Pages\BasketSizePage;
use App\Models\BasketSizeRecord;
use App\Models\Branch;
use App\Models\SalesReport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('esb.base_url', 'https://esb.test');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));

    $this->finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $this->finance->assignRole('FINANCE_STAFF');
    $this->actingAs($this->finance);

    $this->branch = Branch::factory()->create(['name' => 'Kitchen Jateng']);
    $this->branch->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16', 'label' => 'DINE IN', 'is_active' => true]);
    $this->travelTo('2026-09-12 20:00:00');
});

function recalculablePage(array $filters = []): Testable
{
    return Livewire::test(BasketSizePage::class)
        ->set('dateFrom', $filters['from'] ?? '2026-09-01')
        ->set('dateTo', $filters['to'] ?? '2026-09-12')
        ->set('branchId', $filters['branch'] ?? null);
}

it('recalculates recorded shifts with the current master shift hours after the hours were changed', function () {
    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');
    $record->update(['finalized_at' => now()]);
    $this->branch->salesShifts()->where('shift_number', 1)->update(['start_time' => '09:00', 'end_time' => '17:00']);
    fakeEsbSales([
        finalizeSale('BEFORE-NEW-START', '2026-09-10 08:30:00', 40, 1_000_000),
        finalizeSale('IN-WINDOW', '2026-09-10 10:00:00', 10, 200_000),
        finalizeSale('LATE', '2026-09-10 16:00:00', 10, 400_000),
    ]);

    recalculablePage()
        ->assertSee('Hitung Ulang')
        ->assertSee('1 shift pada filter ini')
        ->call('recalculate')
        ->assertHasNoErrors()
        ->assertNotified('Hitung ulang basket size selesai');

    expect($record->fresh())
        ->shift_start_time->toStartWith('09:00')
        ->total_pax->toBe(20)
        ->and((float) $record->fresh()->basket_size)->toBe(30_000.0)
        ->and($record->fresh()->isFinal())->toBeTrue()
        ->and((float) $record->fresh()->employeeRecords()->first()->basket_size_credit)->toBe(30_000.0);
});

it('only recalculates the selected branch and date range', function () {
    $inScope = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');
    $otherDate = submittedShift($this->branch, '2026-09-02', '08:00', '17:00');
    $otherBranch = Branch::factory()->create();
    $otherBranch->esbCodes()->create(['esb_branch_code' => 'BPL2', 'esb_comcode' => 'BLO3', 'label' => 'DINE IN', 'is_active' => true]);
    $otherBranchRecord = submittedShift($otherBranch, '2026-09-10', '08:00', '17:00');
    fakeEsbSales([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)]);

    recalculablePage(['from' => '2026-09-08', 'to' => '2026-09-12', 'branch' => $this->branch->id])
        ->assertSee('1 shift pada filter ini')
        ->call('recalculate')
        ->assertNotified('Hitung ulang basket size selesai');

    expect($inScope->fresh()->isFinal())->toBeTrue()
        ->and($otherDate->fresh()->isFinal())->toBeFalse()
        ->and($otherBranchRecord->fresh()->isFinal())->toBeFalse();
});

it('skips shifts that have not ended yet and reports it', function () {
    $record = submittedShift($this->branch, '2026-09-12', '08:00', '23:00');
    fakeEsbSales([finalizeSale('A', '2026-09-12 10:00:00', 10, 100_000)]);

    recalculablePage()
        ->call('recalculate')
        ->assertNotified('Hitung ulang basket size selesai');

    Http::assertNothingSent();
    expect($record->fresh()->isFinal())->toBeFalse();
});

it('reports shifts that failed because ESB could not be loaded and keeps them provisional', function () {
    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');
    fakeEsbSales([], 500);

    recalculablePage()
        ->call('recalculate')
        ->assertNotified('Hitung ulang basket size selesai');

    expect($record->fresh()->isFinal())->toBeFalse();
});

it('refuses to recalculate more shifts than the limit in a single run', function () {
    foreach (range(0, BasketSizePage::RECALCULATE_LIMIT) as $offset) {
        $date = today()->subDays(40 - $offset)->toDateString();
        $report = SalesReport::factory()->create(['branch_id' => $this->branch->id, 'report_date' => $date]);
        BasketSizeRecord::factory()->create(['sales_report_id' => $report->id, 'branch_id' => $this->branch->id, 'report_date' => $date]);
    }
    fakeEsbSales([]);

    recalculablePage(['from' => today()->subDays(60)->toDateString()])
        ->assertSee('maksimal '.BasketSizePage::RECALCULATE_LIMIT.' per proses')
        ->call('recalculate')
        ->assertNotified('Terlalu banyak shift untuk dihitung ulang sekaligus');

    Http::assertNothingSent();
});

it('validates the date range and reports an empty filter', function () {
    recalculablePage(['from' => '2026-09-12', 'to' => '2026-09-01'])
        ->call('recalculate')
        ->assertHasErrors(['dateTo']);

    recalculablePage()
        ->call('recalculate')
        ->assertNotified('Tidak ada data basket size pada filter ini');
});

it('hides and forbids the recalculation for users without the permission', function () {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->givePermissionTo('view basket sizes');
    $this->actingAs($viewer);
    $record = submittedShift($this->branch, '2026-09-10', '08:00', '17:00');
    fakeEsbSales([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)]);

    recalculablePage()
        ->assertDontSee('Hitung Ulang')
        ->call('recalculate')
        ->assertForbidden();

    Http::assertNothingSent();
    expect($record->fresh()->isFinal())->toBeFalse();
});
