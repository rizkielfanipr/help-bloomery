<?php

use App\Filament\Helpdesk\Pages\BasketSizePage;
use App\Filament\Helpdesk\Resources\Branches\Pages\EditBranch;
use App\Models\BasketSizeRecord;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('esb.base_url', 'https://esb.test');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('helpdesk'));
    $this->travelTo('2026-09-12 20:00:00');

    $this->branchA = Branch::factory()->create(['name' => 'Kitchen Jateng', 'is_active' => true]);
    $this->branchA->esbCodes()->create(['esb_branch_code' => 'BPL', 'esb_comcode' => 'BLO16', 'label' => 'DINE IN', 'is_active' => true]);
    $this->supervisor = User::factory()->create(['is_active' => true]);
    $this->supervisor->assignRole('SUPERVISOR_STORE');
    $this->supervisor->syncBranchAccess([$this->branchA->id], $this->branchA->id);
    $this->actingAs($this->supervisor);

    // A final record calculated yesterday, while the shifts of the branch changed today.
    $this->staleRecord = function (): BasketSizeRecord {
        $record = submittedShift($this->branchA, '2026-09-10', '08:00', '17:00');
        $record->update(['finalized_at' => now()->subDay(), 'calculated_at' => now()->subDay()]);

        return $record;
    };
    $this->openPage = fn () => Livewire::test(BasketSizePage::class)->set('dateFrom', '2026-09-01')->set('dateTo', '2026-09-12');
});

it('stamps the branch only when its shift settings really change', function () {
    $branch = Branch::factory()->create();
    expect($branch->shifts_changed_at)->toBeNull();

    $shift = $branch->salesShifts()->create(['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    expect($branch->fresh()->shifts_changed_at->toDateTimeString())->toBe('2026-09-12 20:00:00');

    $this->travelTo('2026-09-12 21:00:00');
    $shift->save();
    expect($branch->fresh()->shifts_changed_at->toDateTimeString())->toBe('2026-09-12 20:00:00');

    $shift->update(['start_time' => '09:00']);
    expect($branch->fresh()->shifts_changed_at->toDateTimeString())->toBe('2026-09-12 21:00:00');

    $this->travelTo('2026-09-12 22:00:00');
    $shift->delete();
    expect($branch->fresh()->shifts_changed_at->toDateTimeString())->toBe('2026-09-12 22:00:00');
});

it('stamps the branches when the shifts are cleared with the command', function () {
    $this->branchA->salesShifts()->create(['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $this->travelTo('2026-09-12 23:00:00');

    $this->artisan('branch-shifts:clear', ['--force' => true])->assertSuccessful();

    expect($this->branchA->fresh()->shifts_changed_at->toDateTimeString())->toBe('2026-09-12 23:00:00');
});

it('only treats final records calculated before the shift change on ESB branches as outdated', function () {
    $record = ($this->staleRecord)();
    expect(BasketSizeRecord::staleAfterShiftChange()->pluck('id')->all())->toBe([$record->id]);

    $record->update(['finalized_at' => null]);
    expect(BasketSizeRecord::staleAfterShiftChange()->count())->toBe(0);

    $record->update(['finalized_at' => now()->subDay()]);
    $this->branchA->esbCodes()->update(['is_active' => false]);
    expect(BasketSizeRecord::staleAfterShiftChange()->count())->toBe(0);

    $this->branchA->esbCodes()->update(['is_active' => true]);
    $record->update(['calculated_at' => now()]);
    expect(BasketSizeRecord::staleAfterShiftChange()->count())->toBe(0);

    Branch::query()->whereKey($this->branchA->id)->update(['shifts_changed_at' => null]);
    $record->update(['calculated_at' => now()->subDay()]);
    expect(BasketSizeRecord::staleAfterShiftChange()->count())->toBe(0);
});

it('keeps the basket size closed for a supervisor until the outdated shifts are recalculated', function () {
    $employee = ($this->staleRecord)()->employeeRecords()->firstOrFail();

    $page = ($this->openPage)()
        ->assertSee('Hitung ulang dulu sebelum melihat data')
        ->assertSee('setelah 1 shift pada filter ini dihitung')
        ->assertSee('Hitung Ulang Sekarang')
        ->assertSee('Hitung Ulang')
        ->assertSee('Dari Tanggal')
        ->assertDontSee($employee->employee_name);

    expect($page->instance()->mustRecalculateFirst())->toBeTrue()
        ->and($page->instance()->ranking())->toBeEmpty();

    $page->set('employee', $employee->employee_id);
    expect($page->instance()->history())->toBeEmpty();
});

it('opens the data again once the outdated shifts are recalculated', function () {
    $record = ($this->staleRecord)();
    $employeeName = $record->employeeRecords()->firstOrFail()->employee_name;
    fakeEsbSales([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)]);

    $page = ($this->openPage)()->assertSee('Hitung ulang dulu sebelum melihat data');
    runRecalculation($page)->assertNotified('Hitung ulang basket size selesai');

    ($this->openPage)()
        ->assertDontSee('Hitung ulang dulu sebelum melihat data')
        ->assertSee($employeeName);
    expect($record->fresh()->calculated_at->toDateTimeString())->toBe('2026-09-12 20:00:00');
});

it('only informs users who are not required to recalculate', function () {
    $employeeName = ($this->staleRecord)()->employeeRecords()->firstOrFail()->employee_name;
    $finance = User::factory()->create(['is_active' => true, 'access_all_branches' => true]);
    $finance->assignRole('FINANCE_STAFF');
    $this->actingAs($finance);

    $page = ($this->openPage)()
        ->assertDontSee('Hitung ulang dulu sebelum melihat data')
        ->assertSee('dihitung sebelum jam shift cabang terakhir diubah')
        ->assertSee($employeeName);
    expect($page->instance()->mustRecalculateFirst())->toBeFalse();
});

it('does not lock out a supervisor who is not allowed to recalculate', function () {
    $employeeName = ($this->staleRecord)()->employeeRecords()->firstOrFail()->employee_name;
    $limited = User::factory()->create(['is_active' => true]);
    $limited->givePermissionTo(['access backoffice', 'view basket sizes', 'view branches', 'edit branch shifts']);
    $limited->syncBranchAccess([$this->branchA->id], $this->branchA->id);
    $this->actingAs($limited);

    ($this->openPage)()
        ->assertDontSee('Hitung ulang dulu sebelum melihat data')
        ->assertSee('dihitung sebelum jam shift cabang terakhir diubah')
        ->assertSee($employeeName);
});

it('only asks for a recalculation of the shifts in the selected filter', function () {
    ($this->staleRecord)();

    Livewire::test(BasketSizePage::class)
        ->set('dateFrom', '2026-08-01')
        ->set('dateTo', '2026-08-31')
        ->assertDontSee('Hitung ulang dulu sebelum melihat data')
        ->assertDontSee('dihitung sebelum jam shift cabang terakhir diubah');
});

it('asks for the missing shifts before it asks for a recalculation', function () {
    ($this->staleRecord)();
    $this->branchA->salesShifts()->delete();

    ($this->openPage)()
        ->assertSee('Isi Shift Basket Size terlebih dahulu')
        ->assertDontSee('Hitung ulang dulu sebelum melihat data');
});

it('takes a supervisor from changing the shifts to seeing the data again', function () {
    $record = submittedShift($this->branchA, '2026-09-10', '08:00', '17:00');
    $record->update(['finalized_at' => now(), 'calculated_at' => now()]);
    $employeeName = $record->employeeRecords()->firstOrFail()->employee_name;
    fakeEsbSales([finalizeSale('A', '2026-09-10 10:00:00', 10, 100_000)]);

    ($this->openPage)()->assertDontSee('Hitung ulang dulu sebelum melihat data')->assertSee($employeeName);

    $this->travelTo('2026-09-12 21:00:00');
    $edit = Livewire::test(EditBranch::class, ['record' => $this->branchA->id]);
    $state = collect($edit->get('data.salesShifts'))->map(fn (array $item): array => [...$item, 'start_time' => '09:00'])->all();
    $edit->fillForm(['salesShifts' => $state])->call('save')->assertHasNoFormErrors();

    $page = ($this->openPage)()
        ->assertSee('Hitung ulang dulu sebelum melihat data')
        ->assertDontSee($employeeName);

    runRecalculation($page)->assertNotified('Hitung ulang basket size selesai');

    ($this->openPage)()->assertDontSee('Hitung ulang dulu sebelum melihat data')->assertSee($employeeName);
    expect($record->fresh()->shift_start_time)->toStartWith('09:00');
    Http::assertSentCount(1);
});
