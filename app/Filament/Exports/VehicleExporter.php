<?php

namespace App\Filament\Exports;

use App\Models\Vehicle;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class VehicleExporter extends Exporter
{
    protected static ?string $model = Vehicle::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('license_plate')->label('Nomor Polisi'),
            ExportColumn::make('brand')->label('Merek'),
            ExportColumn::make('model')->label('Model'),
            ExportColumn::make('year')->label('Tahun'),
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
