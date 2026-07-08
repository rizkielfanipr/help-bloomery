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
            ExportColumn::make('modal_shift_1')->label('Modal Shift 1'),
            ExportColumn::make('modal_shift_2')->label('Modal Shift 2'),
            ExportColumn::make('submittedBy.name')->label('Disubmit Oleh'),
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
