<?php

namespace App\Console\Commands;

use App\Models\BasketSizeRecord;
use App\Services\BasketSizeFinalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('basket-size:finalize {--branch= : ID cabang tertentu} {--from= : Tanggal laporan mulai (Y-m-d), default 3 hari lalu} {--to= : Tanggal laporan akhir (Y-m-d), default hari ini} {--force : Hitung ulang juga record yang sudah final} {--dry-run : Hanya tampilkan jumlah record tanpa mengubah data}')]
#[Description('Hitung basket size dari jam shift lengkap di master cabang setelah shift berakhir, tanpa menunggu submit staff')]
class BasketSizeFinalizeCommand extends Command
{
    public function handle(BasketSizeFinalizer $finalizer): int
    {
        $from = $this->option('from') ?: today()->subDays(3)->toDateString();
        $to = $this->option('to') ?: today()->toDateString();

        $records = BasketSizeRecord::query()
            ->with(['salesReport.branch.activeSalesShifts', 'salesReport.branch.esbCodes'])
            ->whereDate('report_date', '>=', $from)
            ->whereDate('report_date', '<=', $to)
            ->when($this->option('branch'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when(! $this->option('force'), fn ($query) => $query->whereNull('finalized_at'))
            ->orderBy('report_date')
            ->orderBy('id')
            ->get();

        $result = $finalizer->process($records, (bool) $this->option('dry-run'));

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        $label = $this->option('dry-run') ? 'akan dihitung' : 'dihitung';
        $this->info("Basket size {$label}: {$result['finalized']}. Menunggu shift berakhir: {$result['waiting']}. Dilewati (tanpa ESB): {$result['skipped']}. Gagal: {$result['failed']}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
