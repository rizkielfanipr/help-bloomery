<?php

namespace App\Filament\Exports;

use App\Models\CasualOvertimeRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CasualOvertimeRequestExporter extends Exporter
{
    protected static ?string $model = CasualOvertimeRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('user.name')->label('Staff'),
            ExportColumn::make('clockRecord.date')->label('Tanggal Kerja'),
            ExportColumn::make('requested_hours')->label('Jam Diminta'),
            ExportColumn::make('approved_hours')->label('Jam Disetujui'),
            ExportColumn::make('overtime_fee')->label('Fee Lembur'),
            ExportColumn::make('reason')->label('Alasan'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('created_at')->label('Dicatat Pada'),
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
