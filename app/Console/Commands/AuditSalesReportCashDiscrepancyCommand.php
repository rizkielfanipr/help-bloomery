<?php

namespace App\Console\Commands;

use App\Models\SalesReport;
use App\Models\SalesReportEntry;
use App\Services\EsbService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('sales-reports:audit-cash-discrepancy {--from=} {--to=} {--branch=} {--report=} {--apply}')]
#[Description('Recompute ESB CASH totals for submitted Sales Reports and flag any affected by the change-given-back bug. Read-only by default; pass --apply to write the corrected sales_system_amount.')]
class AuditSalesReportCashDiscrepancyCommand extends Command
{
    public function handle(EsbService $esb): int
    {
        $reportId = $this->option('report');
        $apply = (bool) $this->option('apply');

        $query = SalesReport::query()->whereNotNull('submitted_at')->with(['branch', 'entries']);

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
        /** @var array<int, array{entry: SalesReportEntry, stored: float, recalculated: float}> */
        $discrepancies = [];
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
            $discrepancies[] = ['entry' => $cashEntry, 'stored' => $stored, 'recalculated' => $recalculated];
        }

        if ($rows === []) {
            $this->info("Tidak ditemukan selisih CASH pada {$reports->count()} laporan yang dicek ({$skipped} dilewati karena tidak punya branch/token/entry CASH).");

            return self::SUCCESS;
        }

        $this->table(
            ['Report ID', 'Branch', 'Tanggal', 'Shift', 'Tersimpan (lama)', 'Hasil Hitung Ulang', 'Selisih'],
            $rows,
        );

        if (! $apply) {
            $this->warn(count($rows).' dari '.$reports->count().' laporan terindikasi terdampak bug ini. Ini murni laporan read-only — belum ada data yang diubah. Tambahkan --apply untuk menerapkan koreksi.');

            return self::SUCCESS;
        }

        foreach ($discrepancies as $item) {
            $entry = $item['entry'];
            $note = sprintf(
                '[Koreksi otomatis %s] sales_system_amount CASH diubah dari Rp%s ke Rp%s (bug: paymentAmount ESB termasuk kembalian tunai).',
                now()->toDateTimeString(),
                number_format($item['stored'], 0, ',', '.'),
                number_format($item['recalculated'], 0, ',', '.'),
            );

            $entry->update([
                'sales_system_amount' => $item['recalculated'],
                'notes' => trim(($entry->notes ? $entry->notes."\n" : '').$note),
            ]);
        }

        $this->info(count($discrepancies).' entry sales_system_amount berhasil dikoreksi ke nilai hasil hitung ulang.');

        return self::SUCCESS;
    }
}
