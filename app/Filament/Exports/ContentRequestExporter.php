<?php

namespace App\Filament\Exports;

use App\Models\ContentRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ContentRequestExporter extends Exporter
{
    protected static ?string $model = ContentRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('code')->label('Kode'),
            ExportColumn::make('requester.name')->label('Pemohon'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('judul_konten')->label('Judul Konten'),
            ExportColumn::make('jenis_konten')->label('Jenis Konten'),
            ExportColumn::make('platform_tujuan')->label('Platform Tujuan'),
            ExportColumn::make('tujuan_konten')->label('Tujuan Konten'),
            ExportColumn::make('link_contoh_konten')->label('Link Contoh Konten'),
            ExportColumn::make('status')->label('Status'),
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
