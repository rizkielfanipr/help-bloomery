<?php

namespace App\Filament\Exports;

use App\Models\BriefingTask;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BriefingTaskExporter extends Exporter
{
    protected static ?string $model = BriefingTask::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('label')->label('Nama Poin'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('period')->label('Periode'),
            ExportColumn::make('submission_type')->label('Jenis Input'),
            ExportColumn::make('group_label')->label('Grup'),
            ExportColumn::make('weight')->label('Bobot'),
            ExportColumn::make('deadline_time')->label('Jam Batas'),
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
