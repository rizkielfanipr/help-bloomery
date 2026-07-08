<?php

namespace App\Filament\Exports;

use App\Models\Branch;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BranchExporter extends Exporter
{
    protected static ?string $model = Branch::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Nama'),
            ExportColumn::make('address')->label('Alamat'),
            ExportColumn::make('lat')->label('Latitude'),
            ExportColumn::make('lng')->label('Longitude'),
            ExportColumn::make('radius_meters')->label('Radius (m)'),
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
