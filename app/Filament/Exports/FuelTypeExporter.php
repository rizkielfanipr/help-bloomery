<?php

namespace App\Filament\Exports;

use App\Models\FuelType;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class FuelTypeExporter extends Exporter
{
    protected static ?string $model = FuelType::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Nama'),
            ExportColumn::make('price_per_liter')->label('Harga/Liter'),
            ExportColumn::make('is_active')->label('Aktif'),
            ExportColumn::make('sort_order')->label('Urutan'),
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
