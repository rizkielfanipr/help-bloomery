<?php

namespace App\Filament\Exports;

use App\Models\ServiceRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ServiceRequestExporter extends Exporter
{
    protected static ?string $model = ServiceRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('code')->label('Kode'),
            ExportColumn::make('scheduledBy.name')->label('Pemohon'),
            ExportColumn::make('technician.name')->label('Teknisi'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('scheduled_date')->label('Tanggal Penjadwalan'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('warranty_expires_at')->label('Garansi Hingga'),
            ExportColumn::make('requestor_notes')->label('Catatan Pemohon'),
            ExportColumn::make('created_at')->label('Tanggal'),
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
