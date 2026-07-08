<?php

namespace App\Filament\Exports;

use App\Models\CasualPosition;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CasualPositionExporter extends Exporter
{
    protected static ?string $model = CasualPosition::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Nama Posisi'),
            ExportColumn::make('fee_per_day')->label('Fee/Hari'),
            ExportColumn::make('overtime_rate_per_hour')->label('Rate Lembur/Jam'),
            ExportColumn::make('is_active')->label('Aktif'),
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
