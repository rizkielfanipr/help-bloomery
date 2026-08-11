<?php

namespace App\Filament\Exports;

use App\Models\SalesReport;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SalesReportExporter extends Exporter
{
    protected static ?string $model = SalesReport::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('branch.name')->label('Cabang'),
            ExportColumn::make('report_date')->label('Tanggal'),
            ExportColumn::make('shift_submissions')
                ->label('Shift Terkirim')
                ->state(fn (SalesReport $record): string => $record->shiftSubmissions
                    ->sortBy('shift_number')
                    ->map(fn ($submission) => 'Shift '.$submission->shift_number)
                    ->join(', ')),
            ExportColumn::make('submitted_at')->label('Waktu Submit'),
            ExportColumn::make('submitted_by')
                ->label('Disubmit Oleh')
                ->state(fn (SalesReport $record): string => $record->shiftSubmissions
                    ->pluck('submittedBy.name')
                    ->filter()
                    ->unique()
                    ->join(', ')),
            ExportColumn::make('total_system')
                ->label('Sales System')
                ->state(fn (SalesReport $record): float => $record->total_system),
            ExportColumn::make('total_store')
                ->label('Sales Store')
                ->state(fn (SalesReport $record): float => $record->total_store),
            ExportColumn::make('total_settlement')
                ->label('Settlement')
                ->state(fn (SalesReport $record): float => $record->total_settlement),
            ExportColumn::make('employee_codes')
                ->label('ID Employee')
                ->state(fn (SalesReport $record): string => $record->employees->pluck('employee_code')->filter()->join(', ')),
            ExportColumn::make('employee_names')
                ->label('Staff In Charge')
                ->state(fn (SalesReport $record): string => $record->employees->pluck('employee_name')->filter()->join(', ')),
            ExportColumn::make('employee_positions')
                ->label('Posisi Employee')
                ->state(fn (SalesReport $record): string => $record->employees->pluck('employee_position')->filter()->join(', ')),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('supervisorReviewer.name')->label('Reviewer SPV'),
            ExportColumn::make('supervisor_reviewed_at')->label('Waktu Approval SPV'),
            ExportColumn::make('supervisor_note')->label('Catatan SPV'),
            ExportColumn::make('financeReviewer.name')->label('Reviewer Finance'),
            ExportColumn::make('finance_reviewed_at')->label('Waktu Approval Finance'),
            ExportColumn::make('finance_note')->label('Catatan Finance'),
            ExportColumn::make('created_at')->label('Dibuat'),
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
