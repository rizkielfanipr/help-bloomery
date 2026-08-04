<?php

namespace App\Console\Commands;

use App\Models\SalesReport;
use App\Services\EsbService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('sales-reports:audit-cash-discrepancy {--from=} {--to=} {--branch=}')]
#[Description('Read-only: recompute ESB CASH totals for submitted Sales Reports and flag any affected by the change-given-back bug. Does not modify any data.')]
class AuditSalesReportCashDiscrepancyCommand extends Command
{
    public function handle(EsbService $esb): int
    {
        $from = Carbon::parse($this->option('from') ?? now()->subDays(30)->toDateString());
        $to = Carbon::parse($this->option('to') ?? now()->toDateString());
        $branchId = $this->option('branch');

        $reports = SalesReport::query()
            ->whereNotNull('submitted_at')
            ->whereDate('report_date', '>=', $from->toDateString())
            ->whereDate('report_date', '<=', $to->toDateString())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->with(['branch', 'entries'])
            ->orderBy('report_date')
            ->get();

        if ($reports->isEmpty()) {
            $this->info('Tidak ada Sales Report yang cocok pada rentang tersebut.');

            return self::SUCCESS;
        }

        $rows = [];
        $skipped = 0;

        foreach ($reports as $report) {
            $branch = $report->branch;
            $cashEntry = $report->entries->first(
                fn ($entry) => mb_strtoupper(trim((string) $entry->payment_method_name)) === 'CASH',
            );

            if (! $branch?->esb_branch_code || ! $branch->esb_token || ! $cashEntry) {
                $skipped++;

                continue;
            }

            try {
                $summary = $esb->getShiftPaymentSummary(
                    $branch->esb_branch_code,
                    $report->report_date->toDateString(),
                    $report->shift_started_at?->format('H:i') ?? '00:00',
                    $report->shift_ended_at?->format('H:i') ?? '23:59',
                    $branch->esb_token,
                );
            } catch (Throwable $exception) {
                $this->warn("Gagal fetch ESB untuk report #{$report->id}: {$exception->getMessage()}");
                $skipped++;

                continue;
            }

            $recalculated = (float) (collect($summary['rows'])->firstWhere('name', 'CASH')['total'] ?? 0.0);
            $stored = (float) $cashEntry->sales_system_amount;
            $diff = round($stored - $recalculated, 2);

            if (abs($diff) < 0.01) {
                continue;
            }

            $rows[] = [
                $report->id,
                $branch->name,
                $report->report_date->toDateString(),
                $report->shift_number,
                number_format($stored, 0, ',', '.'),
                number_format($recalculated, 0, ',', '.'),
                number_format($diff, 0, ',', '.'),
            ];
        }

        if ($rows === []) {
            $this->info("Tidak ditemukan selisih CASH pada {$reports->count()} laporan yang dicek ({$skipped} dilewati karena tidak punya branch/token/entry CASH).");

            return self::SUCCESS;
        }

        $this->table(
            ['Report ID', 'Branch', 'Tanggal', 'Shift', 'Tersimpan (lama)', 'Hasil Hitung Ulang', 'Selisih'],
            $rows,
        );
        $this->warn(count($rows).' dari '.$reports->count().' laporan terindikasi terdampak bug ini. Ini murni laporan read-only — belum ada data yang diubah.');

        return self::SUCCESS;
    }
}
