<?php

namespace App\Actions;

use App\Enums\SalesReportStatus;
use App\Models\SalesReport;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportSalesReportsXlsxAction
{
    public function execute(Builder $query, ?string $dateFrom = null, ?string $dateUntil = null): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'sales_reports_');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers(), $this->headerStyle()));

        $query
            ->with([
                'branch:id,name',
                'entries',
                'reconciliations',
                'employees',
                'shiftSubmissions.submittedBy:id,name',
                'supervisorReviewer:id,name',
                'financeReviewer:id,name',
            ])
            ->when($dateFrom, fn (Builder $query, string $date): Builder => $query->whereDate('report_date', '>=', $date))
            ->when($dateUntil, fn (Builder $query, string $date): Builder => $query->whereDate('report_date', '<=', $date))
            ->chunkById(500, function ($reports) use ($writer): void {
                foreach ($reports as $report) {
                    $writer->addRow(Row::fromValues($this->row($report)));
                }
            });

        $writer->close();

        return response()->download($path, $this->filename($dateFrom, $dateUntil), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @return array<int, string> */
    private function headers(): array
    {
        return [
            'ID', 'Cabang', 'Tanggal', 'Shift Terkirim', 'Waktu Submit', 'Disubmit Oleh',
            'Sales System', 'Sales Store', 'Settlement', 'ID Employee', 'Staff In Charge',
            'Posisi Employee', 'Status', 'Reviewer SPV', 'Waktu Approval SPV', 'Catatan SPV',
            'Reviewer Finance', 'Waktu Approval Finance', 'Catatan Finance', 'Dibuat',
        ];
    }

    /** @return array<int, float|int|string> */
    private function row(SalesReport $report): array
    {
        return [
            $report->id,
            $this->safeText($report->branch?->name),
            $report->report_date?->format('Y-m-d') ?? '',
            $this->safeText($report->shiftSubmissions
                ->sortBy('shift_number')
                ->map(fn ($submission): string => 'Shift '.$submission->shift_number)
                ->join(', ')),
            $report->submitted_at?->format('Y-m-d H:i:s') ?? '',
            $this->safeText($report->shiftSubmissions->pluck('submittedBy.name')->filter()->unique()->join(', ')),
            $report->total_system,
            $report->total_store,
            $report->total_settlement,
            $this->safeText($report->employees->pluck('employee_code')->filter()->join(', ')),
            $this->safeText($report->employees->pluck('employee_name')->filter()->join(', ')),
            $this->safeText($report->employees->pluck('employee_position')->filter()->join(', ')),
            $report->status instanceof SalesReportStatus ? $report->status->getLabel() : (string) $report->status,
            $this->safeText($report->supervisorReviewer?->name),
            $report->supervisor_reviewed_at?->format('Y-m-d H:i:s') ?? '',
            $this->safeText($report->supervisor_note),
            $this->safeText($report->financeReviewer?->name),
            $report->finance_reviewed_at?->format('Y-m-d H:i:s') ?? '',
            $this->safeText($report->finance_note),
            $report->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    private function filename(?string $dateFrom, ?string $dateUntil): string
    {
        $from = $dateFrom ?: 'semua';
        $until = $dateUntil ?: 'semua';

        return "sales-report-{$from}-sampai-{$until}.xlsx";
    }

    private function headerStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setBackgroundColor('2563EB')
            ->setFontColor(Color::WHITE);
    }

    private function safeText(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $text) === 1 ? "'{$text}" : $text;
    }
}
