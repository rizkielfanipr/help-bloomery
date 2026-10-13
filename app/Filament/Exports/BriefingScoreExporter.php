<?php

namespace App\Filament\Exports;

use App\Models\BriefingScore;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BriefingScoreExporter extends Exporter
{
    protected static ?string $model = BriefingScore::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('year')->label('Tahun'),
            ExportColumn::make('month')->label('Bulan'),
            ExportColumn::make('score')->label('Skor'),
            ExportColumn::make('computed_at')->label('Dihitung Pada'),
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
