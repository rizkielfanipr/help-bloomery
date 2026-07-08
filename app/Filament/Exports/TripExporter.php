<?php

namespace App\Filament\Exports;

use App\Models\Trip;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TripExporter extends Exporter
{
    protected static ?string $model = Trip::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('code')->label('Kode'),
            ExportColumn::make('driver.name')->label('Driver'),
            ExportColumn::make('tripRoute.name')->label('Rute'),
            ExportColumn::make('vehicle.license_plate')->label('Kendaraan'),
            ExportColumn::make('trip_date')->label('Tanggal'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('started_at')->label('Mulai'),
            ExportColumn::make('completed_at')->label('Selesai'),
            ExportColumn::make('meal_allowance_amount')->label('Uang Makan'),
            ExportColumn::make('has_fuel_fillup')->label('Ada BBM'),
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
