<?php

namespace App\Console\Commands;

use App\Enums\SalesReportStatus;
use App\Models\BasketSizeRecord;
use App\Models\Branch;
use App\Models\BranchSalesShift;
use App\Models\SalesReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('branch-shifts:clear {--branch= : ID cabang tertentu (default semua cabang)} {--dry-run : Hanya tampilkan yang akan dihapus tanpa mengubah data} {--force : Lewati konfirmasi} {--restore= : Kembalikan shift dari berkas cadangan yang dibuat perintah ini}')]
#[Description('One-off: hapus pengaturan Shift Basket Size di master cabang (cabang kembali ke shift bawaan). Berkas cadangan dibuat otomatis dan bisa dipulihkan dengan --restore')]
class ClearBranchSalesShiftsCommand extends Command
{
    public function handle(): int
    {
        if ($file = $this->option('restore')) {
            return $this->restore((string) $file);
        }

        $shifts = BranchSalesShift::query()
            ->when($this->option('branch'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->orderBy('branch_id')
            ->orderBy('shift_number')
            ->get();

        if ($shifts->isEmpty()) {
            $this->info('Tidak ada pengaturan shift yang perlu dihapus.');

            return self::SUCCESS;
        }

        $branches = Branch::query()->whereIn('id', $shifts->pluck('branch_id')->unique())->orderBy('name')->get()->keyBy('id');

        $this->table(
            ['Cabang', 'Shift yang dihapus', 'Kembali ke', 'Laporan berjalan (Draft)', 'Basket Size sementara'],
            $branches->map(fn (Branch $branch): array => [
                $branch->name,
                $shifts->where('branch_id', $branch->id)->map(fn (BranchSalesShift $shift): string => "{$shift->shift_number}: ".substr($shift->start_time, 0, 5).'–'.substr($shift->end_time, 0, 5).($shift->is_active ? '' : ' (nonaktif)'))->join(' | '),
                max(1, (int) $branch->sales_shift_count).' shift bawaan',
                SalesReport::query()->where('branch_id', $branch->id)->where('status', SalesReportStatus::Draft->value)->count(),
                BasketSizeRecord::query()->where('branch_id', $branch->id)->whereNull('finalized_at')->count(),
            ])->values()->all(),
        );

        $this->warn('Cabang tanpa pengaturan shift memakai jumlah shift bawaan dengan jam 07:00–15:00 dan 15:00–23:00, sehingga jumlah shift yang wajib di-submit dan jendela jam ESB Sales Report ikut berubah.');
        $this->line('Sebaiknya jalankan saat tidak ada laporan berjalan (Draft), dan setelah `php artisan basket-size:finalize` untuk Basket Size sementara agar dihitung dengan jam yang lama.');

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$shifts->count()} pengaturan shift di {$branches->count()} cabang akan dihapus. Tidak ada data yang diubah.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Hapus {$shifts->count()} pengaturan shift di {$branches->count()} cabang?")) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $path = $this->backup($shifts);

        DB::transaction(fn () => BranchSalesShift::query()->whereIn('id', $shifts->pluck('id'))->delete());

        $this->info("{$shifts->count()} pengaturan shift di {$branches->count()} cabang dihapus.");
        $this->line("Cadangan: {$path}");
        $this->line("Untuk mengembalikan: php artisan branch-shifts:clear --restore={$path}");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, BranchSalesShift>  $shifts
     */
    private function backup($shifts): string
    {
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/branch-sales-shifts-'.now()->format('Ymd-His').'.json';

        File::put($path, json_encode(
            $shifts->map(fn (BranchSalesShift $shift): array => $shift->only(['id', 'branch_id', 'shift_number', 'name', 'start_time', 'end_time', 'is_active']))->values()->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        ));

        return $path;
    }

    private function restore(string $path): int
    {
        if (! File::exists($path)) {
            $this->error("Berkas cadangan tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode(File::get($path), true);

        if (! is_array($rows)) {
            $this->error('Berkas cadangan tidak valid.');

            return self::FAILURE;
        }

        $restored = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$restored, &$skipped): void {
            foreach ($rows as $row) {
                $exists = BranchSalesShift::query()->where('branch_id', $row['branch_id'])->where('shift_number', $row['shift_number'])->exists();

                if ($exists || ! Branch::query()->whereKey($row['branch_id'])->exists()) {
                    $skipped++;

                    continue;
                }

                $shift = new BranchSalesShift(collect($row)->except('id')->all());
                if (! BranchSalesShift::query()->whereKey($row['id'])->exists()) {
                    $shift->id = $row['id'];
                }
                $shift->save();
                $restored++;
            }
        });

        $this->info("{$restored} pengaturan shift dikembalikan, {$skipped} dilewati (sudah ada atau cabang tidak ditemukan).");

        return self::SUCCESS;
    }
}
