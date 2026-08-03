<?php

namespace App\Filament\Exports;

use App\Models\PurchaseRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseRequestExporter extends Exporter
{
    protected static ?string $model = PurchaseRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('item_name')->label('Nama Barang'),
            ExportColumn::make('quantity')->label('Qty'),
            ExportColumn::make('purchase_type')->label('Jenis'),
            ExportColumn::make('purchase_reason')->label('Alasan'),
            ExportColumn::make('purchase_request_number')->label('No. Permintaan'),
            ExportColumn::make('journal_item_number')->label('No. Jurnal'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('admin_notes')->label('Catatan Admin'),
            ExportColumn::make('processedBy.name')->label('Diproses Oleh'),
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
