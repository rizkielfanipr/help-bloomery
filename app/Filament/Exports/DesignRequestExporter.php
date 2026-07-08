<?php

namespace App\Filament\Exports;

use App\Models\DesignRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class DesignRequestExporter extends Exporter
{
    protected static ?string $model = DesignRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('requester.name')->label('Pemohon'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('category.name')->label('Kategori'),
            ExportColumn::make('judul_permintaan')->label('Judul'),
            ExportColumn::make('ringkasan_brief')->label('Brief'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('assignee.name')->label('PIC'),
            ExportColumn::make('created_at')->label('Tanggal'),
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
