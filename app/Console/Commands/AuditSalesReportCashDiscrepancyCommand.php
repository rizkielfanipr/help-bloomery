<?php

namespace App\Console\Commands;

use App\Models\SalesReport;
use App\Models\SalesReportReconciliation;
use App\Services\EsbService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sales-reports:audit-cash-discrepancy {--from=} {--to=} {--branch=} {--report=} {--apply}')]
#[Description('Recompute ESB CASH totals for submitted Sales Reports and flag any affected by the change-given-back bug. Read-only by default; pass --apply to write the corrected system_amount.')]
class AuditSalesReportCashDiscrepancyCommand extends Command
{
    public function handle(EsbService $esb): int
    {
        $reportId = $this->option('report');
        $apply = (bool) $this->option('apply');

        $query = SalesReport::query()->whereNotNull('submitted_at')->with(['branch.esbCodes', 'reconciliations']);

        if ($reportId) {
            $query->where('id', $reportId);
        } else {
            $from = Carbon::parse($this->option('from') ?? now()->subDays(30)->toDateString());
            $to = Carbon::parse($this->option('to') ?? now()->toDateString());
            $branchId = $this->option('branch');

            $query->whereDate('report_date', '>=', $from->toDateString())
                ->whereDate('report_date', '<=', $to->toDateString())
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        }

        $reports = $query->orderBy('report_date')->get();

        if ($reports->isEmpty()) {
            $this->info('Tidak ada Sales Report yang cocok.');

            return self::SUCCESS;
        }

        $rows = [];
        /** @var array<int, array{reconciliation: SalesReportReconciliation, stored: float, recalculated: float}> */
        $discrepancies = [];
        $skipped = 0;

        foreach ($reports as $report) {
            $branch = $report->branch;
            $cashReconciliations = $report->reconciliations->filter(
                fn ($reconciliation) => mb_strtoupper(trim((string) $reconciliation->payment_method_name)) === 'CASH',
            );

            if (! $branch?->hasEsbIntegration() || $cashReconciliations->isEmpty()) {
                $skipped++;

                continue;
            }

            $groups = $esb->getPaymentSummaryByLabelForBranch($branch, $report->report_date->toDateString());
            $allOk = ! empty($groups) && collect($groups)->every(fn (array $group): bool => $group['ok']);

            if (! $allOk) {
                $this->warn("Gagal fetch ESB untuk report #{$report->id} (salah satu atau lebih pasangan kode ESB gagal).");
                $skipped++;

                continue;
            }

            foreach ($cashReconciliations as $cashReconciliation) {
                $labelRows = $groups[$cashReconciliation->label]['rows'] ?? [];
                $recalculated = (float) (collect($labelRows)->firstWhere('name', 'CASH')['total'] ?? 0.0);
                $stored = (float) $cashReconciliation->system_amount;
                $diff = round($stored - $recalculated, 2);

                if (abs($diff) < 0.01) {
                    continue;
                }

                $rows[] = [
                    $report->id,
                    $branch->name,
                    $report->report_date->toDateString(),
                    $cashReconciliation->label,
                    number_format($stored, 0, ',', '.'),
                    number_format($recalculated, 0, ',', '.'),
                    number_format($diff, 0, ',', '.'),
                ];
                $discrepancies[] = ['reconciliation' => $cashReconciliation, 'stored' => $stored, 'recalculated' => $recalculated];
            }
        }

        if ($rows === []) {
            $this->info("Tidak ditemukan selisih CASH pada {$reports->count()} laporan yang dicek ({$skipped} dilewati karena tidak punya branch/token/reconciliation CASH).");

            return self::SUCCESS;
        }

        $this->table(
            ['Report ID', 'Branch', 'Tanggal', 'Label', 'Tersimpan (lama)', 'Hasil Hitung Ulang', 'Selisih'],
            $rows,
        );

        if (! $apply) {
            $this->warn(count($rows).' dari '.$reports->count().' laporan terindikasi terdampak bug ini. Ini murni laporan read-only — belum ada data yang diubah. Tambahkan --apply untuk menerapkan koreksi.');

            return self::SUCCESS;
        }

        foreach ($discrepancies as $item) {
            $reconciliation = $item['reconciliation'];
            $note = sprintf(
                '[Koreksi otomatis %s] system_amount CASH diubah dari Rp%s ke Rp%s (bug: paymentAmount ESB termasuk kembalian tunai).',
                now()->toDateTimeString(),
                number_format($item['stored'], 0, ',', '.'),
                number_format($item['recalculated'], 0, ',', '.'),
            );

            $reconciliation->update([
                'system_amount' => $item['recalculated'],
                'supervisor_notes' => trim(($reconciliation->supervisor_notes ? $reconciliation->supervisor_notes."\n" : '').$note),
            ]);
        }

        $this->info(count($discrepancies).' reconciliation system_amount berhasil dikoreksi ke nilai hasil hitung ulang.');

        return self::SUCCESS;
    }
}
