<?php

use App\Enums\SalesReportStatus;
use App\Models\BasketSizeRecord;
use App\Models\Branch;
use App\Models\BranchSalesShift;
use App\Models\SalesReport;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Kitchen Jateng']);
    $this->branchB = Branch::factory()->create(['name' => 'Kitchen Jakarta']);
    $this->branchA->salesShifts()->create(['shift_number' => 1, 'name' => 'Shift 1', 'start_time' => '08:00', 'end_time' => '17:00', 'is_active' => true]);
    $this->branchA->salesShifts()->create(['shift_number' => 2, 'name' => 'Shift 2', 'start_time' => '17:00', 'end_time' => '23:00', 'is_active' => false]);
    $this->branchB->salesShifts()->create(['shift_number' => 1, 'name' => 'Pagi', 'start_time' => '07:00', 'end_time' => '15:00', 'is_active' => true]);
});

afterEach(function () {
    foreach (glob(storage_path('app/backups/branch-sales-shifts-*.json')) ?: [] as $file) {
        File::delete($file);
    }
});

function latestShiftBackup(): ?string
{
    $files = glob(storage_path('app/backups/branch-sales-shifts-*.json')) ?: [];
    sort($files);

    return $files === [] ? null : end($files);
}

it('clears the shift settings of every branch, keeps recorded basket sizes and writes a backup', function () {
    $record = BasketSizeRecord::factory()->create([
        'sales_report_id' => SalesReport::factory()->create(['branch_id' => $this->branchA->id])->id,
        'branch_id' => $this->branchA->id,
        'branch_sales_shift_id' => $this->branchA->salesShifts()->where('shift_number', 1)->value('id'),
        'shift_name' => 'Shift 1',
        'shift_start_time' => '08:00:00',
        'shift_end_time' => '17:00:00',
    ]);

    $this->artisan('branch-shifts:clear')
        ->expectsConfirmation('Hapus 3 pengaturan shift di 2 cabang?', 'yes')
        ->expectsOutputToContain('3 pengaturan shift di 2 cabang dihapus.')
        ->assertSuccessful();

    expect(BranchSalesShift::count())->toBe(0)
        ->and(Branch::count())->toBe(2)
        ->and($record->fresh())
        ->branch_sales_shift_id->toBeNull()
        ->shift_name->toBe('Shift 1')
        ->shift_start_time->toStartWith('08:00');

    $backup = json_decode(File::get(latestShiftBackup()), true);
    expect($backup)->toHaveCount(3)
        ->and(collect($backup)->pluck('name')->sort()->values()->all())->toBe(['Pagi', 'Shift 1', 'Shift 2']);
});

it('shows what would be removed, the fallback and open work, without changing anything on a dry run', function () {
    SalesReport::factory()->create(['branch_id' => $this->branchA->id, 'status' => SalesReportStatus::Draft->value]);
    BasketSizeRecord::factory()->provisional()->create([
        'sales_report_id' => SalesReport::factory()->create(['branch_id' => $this->branchA->id, 'status' => SalesReportStatus::Completed->value])->id,
        'branch_id' => $this->branchA->id,
    ]);

    $this->artisan('branch-shifts:clear', ['--dry-run' => true])
        ->expectsTable(
            ['Cabang', 'Shift yang dihapus', 'Kembali ke', 'Laporan berjalan (Draft)', 'Basket Size sementara'],
            [
                ['Kitchen Jakarta', '1: 07:00–15:00', '2 shift bawaan', '0', '0'],
                ['Kitchen Jateng', '1: 08:00–17:00 | 2: 17:00–23:00 (nonaktif)', '2 shift bawaan', '1', '1'],
            ],
        )
        ->expectsOutputToContain('Dry run: 3 pengaturan shift di 2 cabang akan dihapus.')
        ->assertSuccessful();

    expect(BranchSalesShift::count())->toBe(3)
        ->and(latestShiftBackup())->toBeNull();
});

it('changes nothing when the confirmation is declined', function () {
    $this->artisan('branch-shifts:clear')
        ->expectsConfirmation('Hapus 3 pengaturan shift di 2 cabang?', 'no')
        ->expectsOutputToContain('Dibatalkan.')
        ->assertSuccessful();

    expect(BranchSalesShift::count())->toBe(3)
        ->and(latestShiftBackup())->toBeNull();
});

it('only clears the requested branch', function () {
    $this->artisan('branch-shifts:clear', ['--branch' => $this->branchA->id, '--force' => true])
        ->expectsOutputToContain('2 pengaturan shift di 1 cabang dihapus.')
        ->assertSuccessful();

    expect($this->branchA->salesShifts()->count())->toBe(0)
        ->and($this->branchB->salesShifts()->count())->toBe(1);
});

it('reports when there is nothing to clear', function () {
    BranchSalesShift::query()->delete();

    $this->artisan('branch-shifts:clear')
        ->expectsOutputToContain('Tidak ada pengaturan shift yang perlu dihapus.')
        ->assertSuccessful();

    expect(latestShiftBackup())->toBeNull();
});

it('restores the cleared shifts from the backup without overwriting shifts entered since', function () {
    $this->artisan('branch-shifts:clear', ['--force' => true])->assertSuccessful();
    $backup = latestShiftBackup();
    $originalIds = collect(json_decode(File::get($backup), true))->pluck('id')->sort()->values()->all();
    $this->branchB->salesShifts()->create(['shift_number' => 1, 'name' => 'Baru', 'start_time' => '09:00', 'end_time' => '18:00', 'is_active' => true]);

    $this->artisan('branch-shifts:clear', ['--restore' => $backup])
        ->expectsOutputToContain('2 pengaturan shift dikembalikan, 1 dilewati')
        ->assertSuccessful();

    expect($this->branchA->salesShifts()->orderBy('shift_number')->pluck('name')->all())->toBe(['Shift 1', 'Shift 2'])
        ->and((bool) $this->branchA->salesShifts()->where('shift_number', 2)->value('is_active'))->toBeFalse()
        ->and($this->branchB->salesShifts()->sole()->name)->toBe('Baru')
        ->and(BranchSalesShift::query()->whereIn('id', $originalIds)->count())->toBe(2);

    $this->artisan('branch-shifts:clear', ['--restore' => $backup])
        ->expectsOutputToContain('0 pengaturan shift dikembalikan, 3 dilewati')
        ->assertSuccessful();
});

it('fails clearly when the backup file is missing or invalid', function () {
    $this->artisan('branch-shifts:clear', ['--restore' => storage_path('app/backups/tidak-ada.json')])
        ->expectsOutputToContain('Berkas cadangan tidak ditemukan')
        ->assertFailed();

    File::ensureDirectoryExists(storage_path('app/backups'));
    $broken = storage_path('app/backups/branch-sales-shifts-rusak.json');
    File::put($broken, 'bukan json');

    $this->artisan('branch-shifts:clear', ['--restore' => $broken])
        ->expectsOutputToContain('Berkas cadangan tidak valid.')
        ->assertFailed();
});
