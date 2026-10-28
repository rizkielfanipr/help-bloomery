<?php

namespace App\Actions\SalesReport;

use App\Actions\SalesReport\Concerns\WritesSalesReportXlsx;
use App\Models\SalesReportVoid;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Void export for comparison with the ESB report
 * (docs/products/finance/sales-report-compliment-void-remediation/prd.md §12.2): one row per Void, from the
 * already filtered and branch-scoped query of the report page. Evidence columns state
 * availability only; no temporary URL is exported.
 */
class ExportSalesReportVoidsXlsxAction
{
    use WritesSalesReportXlsx;

    /** @var list<string> */
    public const HEADERS = [
        'Void ID', 'Sales Report ID', 'Tanggal Laporan', 'Branch Code', 'Branch Name', 'Company Code', 'Shift',
        'Sales Number', 'Sumber Sales Number', 'Tanggal/Waktu Transaksi ESB', 'Nilai Transaksi ESB', 'Payment Method',
        'Alasan Void', 'Foto Nota Awal Tersedia', 'Foto Setelah Void Tersedia', 'Disubmit Oleh', 'Waktu Submit', 'Status Sales Report',
    ];

    public function execute(Builder $query, ?string $dateFrom = null, ?string $dateUntil = null): BinaryFileResponse
    {
        $query = $query->clone()
            ->with(['salesReport:id,branch_id,report_date,status', 'salesReport.branch:id,name', 'submittedBy:id,name']);
        $timezone = (string) config('app.timezone');

        return $this->download($query, self::HEADERS, fn (SalesReportVoid $void): array => [
            $void->id,
            $void->sales_report_id,
            $void->salesReport?->report_date?->format('Y-m-d') ?? '',
            $this->safeText($void->source_branch_code),
            $this->safeText($void->salesReport?->branch?->name),
            $this->safeText($void->source_comcode),
            $void->shift_number,
            $this->safeText($void->sales_number),
            $void->sales_number_source->getLabel(),
            $void->sales_transaction_at?->format('Y-m-d H:i:s') ?? '',
            $void->transaction_amount !== null ? (float) $void->transaction_amount : '',
            $this->safeText($void->payment_method_name),
            $this->safeText($void->reason),
            filled($void->original_receipt_path) ? 'Ya' : 'Tidak',
            filled($void->voided_receipt_path) ? 'Ya' : 'Tidak',
            $this->safeText($void->submittedBy?->name),
            $void->created_at?->timezone($timezone)->format('Y-m-d H:i:s') ?? '',
            $void->salesReport?->status?->getLabel() ?? '',
        ], $this->filename('void', $dateFrom, $dateUntil));
    }
}
