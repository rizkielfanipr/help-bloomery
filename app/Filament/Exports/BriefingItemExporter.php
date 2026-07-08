<?php

namespace App\Filament\Exports;

use App\Models\BriefingItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class BriefingItemExporter extends Exporter
{
    protected static ?string $model = BriefingItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('briefingRecord.user.name')->label('Staff'),
            ExportColumn::make('briefingRecord.branch.name')->label('Cabang'),
            ExportColumn::make('task_key')->label('Tugas'),
            ExportColumn::make('period')->label('Periode'),
            ExportColumn::make('record_date')->label('Tanggal'),
            ExportColumn::make('completed_at')->label('Waktu Selesai'),
            ExportColumn::make('review_status')->label('Status Review'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['briefingRecord.user', 'briefingRecord.branch']);
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
