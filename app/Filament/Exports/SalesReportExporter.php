<?php

namespace App\Filament\Exports;

use App\Models\SalesReport;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SalesReportExporter extends Exporter
{
    protected static ?string $model = SalesReport::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('report_date')->label('Tanggal'),
            ExportColumn::make('shift_number')->label('Shift'),
            ExportColumn::make('shift_started_at')->label('Shift Mulai'),
            ExportColumn::make('shift_ended_at')->label('Shift Selesai'),
            ExportColumn::make('submitted_at')->label('Waktu Submit'),
            ExportColumn::make('submittedBy.name')->label('Disubmit Oleh'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('supervisorReviewer.name')->label('Reviewer SPV'),
            ExportColumn::make('supervisor_reviewed_at')->label('Waktu Approval SPV'),
            ExportColumn::make('supervisor_note')->label('Catatan SPV'),
            ExportColumn::make('financeReviewer.name')->label('Reviewer Finance'),
            ExportColumn::make('finance_reviewed_at')->label('Waktu Approval Finance'),
            ExportColumn::make('finance_note')->label('Catatan Finance'),
            ExportColumn::make('rejection_reason')->label('Alasan Penolakan'),
            ExportColumn::make('created_at')->label('Dibuat'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export selesai. '.number_format($export->successful_rows).' baris berhasil diexport.';
        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' baris gagal.';
        }

        return $body;
    }
}
