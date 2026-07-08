<?php

namespace App\Filament\Exports;

use App\Models\CasualClockRecord;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CasualClockRecordExporter extends Exporter
{
    protected static ?string $model = CasualClockRecord::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('user.name')->label('Staff'),
            ExportColumn::make('user.casualPosition.name')->label('Posisi'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('date')->label('Tanggal'),
            ExportColumn::make('clock_in_at')->label('Clock In'),
            ExportColumn::make('clock_out_at')->label('Clock Out'),
            ExportColumn::make('notes')->label('Catatan'),
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
