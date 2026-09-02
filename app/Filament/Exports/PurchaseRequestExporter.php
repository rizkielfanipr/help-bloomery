<?php

namespace App\Filament\Exports;

use App\Enums\PurchaseRequestStatus;
use App\Enums\PurchaseType;
use App\Models\PurchaseRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRequestExporter extends Exporter
{
    protected static ?string $model = PurchaseRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('code')->label('Kode Internal')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('purchase_request_number')->label('No. Permintaan')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('journal_item_number')->label('No. Jurnal')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('created_at')->label('Tanggal Pengajuan')->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d H:i:s')),
            ExportColumn::make('updated_at')->label('Terakhir Diperbarui')->formatStateUsing(fn ($state): ?string => $state?->format('Y-m-d H:i:s')),
            ExportColumn::make('user_id')->label('User ID'),
            ExportColumn::make('user.name')->label('Pemohon')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('user.username')->label('Username Pemohon')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('branch_id')->label('Branch ID'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('division')->label('Divisi')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('item_name')->label('Nama Barang')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('quantity')->label('Qty'),
            ExportColumn::make('purchase_type')->label('Jenis Pembelian')->formatStateUsing(
                fn (PurchaseType|string|null $state): string => $state instanceof PurchaseType ? $state->getLabel() : (string) $state
            ),
            ExportColumn::make('purchase_reason')->label('Alasan Pembelian')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('ecommerce_link')->label('Link E-commerce')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('attachment_paths')->label('Lampiran')->formatStateUsing(
                fn (array|string|null $state): string => self::safeText(is_array($state) ? implode(' | ', $state) : $state)
            ),
            ExportColumn::make('status')->label('Status')->formatStateUsing(
                fn (PurchaseRequestStatus|string|null $state): string => $state instanceof PurchaseRequestStatus ? $state->getLabel() : (string) $state
            ),
            ExportColumn::make('admin_notes')->label('Catatan Admin')->formatStateUsing(self::safeText(...)),
            ExportColumn::make('processed_by')->label('Processed By ID'),
            ExportColumn::make('processedBy.name')->label('Diproses Oleh'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['user:id,name,username', 'branch:id,name', 'processedBy:id,name']);
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            DatePicker::make('date_from')
                ->label('Tanggal Mulai')
                ->native(false),
            DatePicker::make('date_until')
                ->label('Tanggal Akhir')
                ->native(false)
                ->afterOrEqual('date_from'),
        ];
    }

    /** @param array<string, mixed> $options */
    public static function applyDateRange(Builder $query, array $options): Builder
    {
        return $query
            ->when($options['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($options['date_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));
    }

    public function getFileName(Export $export): string
    {
        $from = $this->options['date_from'] ?? 'semua';
        $until = $this->options['date_until'] ?? 'semua';

        return "permintaan-pembelian-{$from}-sampai-{$until}";
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export selesai. '.number_format($export->successful_rows).' baris berhasil diexport.';
        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' baris gagal.';
        }

        return $body;
    }

    private static function safeText(mixed $state): string
    {
        $value = (string) ($state ?? '');

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
