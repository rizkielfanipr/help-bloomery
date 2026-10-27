<?php

namespace App\Actions\SalesReport;

use App\Actions\SalesReport\Concerns\WritesSalesReportXlsx;
use App\Models\SalesReportCompliment;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Compliment export for comparison with the ESB report
 * (docs/products/finance/sales-report-compliment-void-remediation/prd.md §11.2): one row per Compliment. The
 * caller passes the already filtered and branch-scoped query of the report page.
 *
 * Header names and order are the PRD baseline; they are kept in HEADERS so they can be aligned
 * once Finance supplies the ESB sample file.
 */
class ExportSalesReportComplimentsXlsxAction
{
    use WritesSalesReportXlsx;

    /** @var list<string> */
    public const HEADERS = [
        'Compliment ID', 'Sales Report ID', 'Tanggal Laporan', 'Branch Code', 'Branch Name', 'Company Code', 'Shift',
        'Sales Number', 'Sumber Sales Number', 'Tanggal/Waktu Transaksi ESB', 'Jenis Compliment', 'Keterangan Transaksi',
        'Jumlah Foto', 'Bukti Tersedia', 'Disubmit Oleh', 'Waktu Submit', 'Status Sales Report',
    ];

    public function execute(Builder $query, ?string $dateFrom = null, ?string $dateUntil = null): BinaryFileResponse
    {
        $query = $query->clone()
            ->with(['salesReport:id,branch_id,report_date,status', 'salesReport.branch:id,name', 'submittedBy:id,name']);
        $timezone = (string) config('app.timezone');

        return $this->download($query, self::HEADERS, fn (SalesReportCompliment $compliment): array => [
            $compliment->id,
            $compliment->sales_report_id,
            $compliment->salesReport?->report_date?->format('Y-m-d') ?? '',
            $this->safeText($compliment->source_branch_code),
            $this->safeText($compliment->salesReport?->branch?->name),
            $this->safeText($compliment->source_comcode),
            $compliment->shift_number,
            $this->safeText($compliment->sales_number),
            $compliment->effectiveSalesNumberSource()->getLabel(),
            $compliment->sales_transaction_at?->format('Y-m-d H:i:s') ?? '',
            $this->safeText($compliment->compliment_type_name),
            $this->safeText($compliment->notes),
            count($compliment->attachment_paths ?? []),
            ($compliment->attachment_paths ?? []) === [] ? 'Tidak' : 'Ya',
            $this->safeText($compliment->submittedBy?->name),
            $compliment->created_at?->timezone($timezone)->format('Y-m-d H:i:s') ?? '',
            $compliment->salesReport?->status?->getLabel() ?? '',
        ], $this->filename('compliment', $dateFrom, $dateUntil));
    }
}
