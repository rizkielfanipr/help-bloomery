<?php

namespace App\Filament\Helpdesk\Resources\QualityControlItemJournals\Schemas;

use App\Models\QualityControlItemJournal;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QualityControlItemJournalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Item Journal')->schema([
                    TextEntry::make('item_journal_number')->label('Nomor Item Journal')->copyable()->placeholder('Menunggu nomor ESB'),
                    TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                        'submitting' => 'Mengirim',
                        'unknown' => 'Perlu Rekonsiliasi',
                        'succeeded' => 'Berhasil',
                        'attachment_failed' => 'Attachment Gagal',
                        'verification_required' => 'Perlu Verifikasi',
                        default => $state,
                    })->color(fn (string $state): string => match ($state) {
                        'succeeded' => 'success',
                        'submitting' => 'warning',
                        'unknown', 'attachment_failed', 'verification_required' => 'danger',
                        default => 'gray',
                    }),
                    TextEntry::make('journal_date')->label('Tanggal')->date('d M Y'),
                    TextEntry::make('creator.name')->label('Dibuat Oleh'),
                    TextEntry::make('esb_comcode')->label('Company Code')->badge(),
                    TextEntry::make('esb_branch_name')->label('Cabang')->formatStateUsing(fn (?string $state, QualityControlItemJournal $record): string => collect([$record->esb_branch_code, $state])->filter()->implode(' · ')),
                    TextEntry::make('location_name')->label('Lokasi'),
                    TextEntry::make('submitted_at')->label('Dikirim Pada')->dateTime('d M Y H:i')->placeholder('—'),
                    TextEntry::make('additional_info')->label('Informasi Tambahan')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('last_error')->label('Error Terakhir')->placeholder('—')->color('danger')->columnSpanFull(),
                ])->columns(4),
                Section::make('Detail Produk')->schema([
                    RepeatableEntry::make('details')->label('')->schema([
                        TextEntry::make('product_code')->label('Kode Produk')->copyable()->placeholder('—'),
                        TextEntry::make('product_name')->label('Nama Produk'),
                        TextEntry::make('purpose_name')->label('Purpose')->formatStateUsing(fn (?string $state, $record): string => collect([$state, $record->purpose_account])->filter()->implode(' · '))->placeholder('—'),
                        TextEntry::make('qty')->label('Qty')->numeric(decimalPlaces: 4)->suffix(fn ($record): string => ' '.($record->uom_name ?: '')),
                        TextEntry::make('hpp')->label('HPP')->money('IDR'),
                    ])->columns(5),
                ]),
                Section::make('Attachment')->schema([
                    RepeatableEntry::make('attachments')->label('')->schema([
                        TextEntry::make('original_name')->label('Nama File'),
                        TextEntry::make('mime_type')->label('Tipe File')->badge(),
                        TextEntry::make('file_size')->label('Ukuran')->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 1).' KB' : '—'),
                        TextEntry::make('uploaded_to_esb_at')->label('Dikirim ke ESB')->dateTime('d M Y H:i')->placeholder('Belum dikirim'),
                        TextEntry::make('esb_url')->label('File ESB')->formatStateUsing(fn (?string $state): string => $state ? 'Buka Attachment' : 'Belum tersedia')->url(fn (?string $state): ?string => $state)->openUrlInNewTab(),
                    ])->columns(5),
                ])->collapsible(),
                Section::make('Data Integrasi ESB')->collapsed()->schema([
                    TextEntry::make('request_payload')->label('Request Payload')->state(fn (QualityControlItemJournal $record): string => json_encode($record->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')->fontFamily('mono')->copyable(),
                    TextEntry::make('response_payload')->label('Response Payload')->state(fn (QualityControlItemJournal $record): string => json_encode($record->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')->fontFamily('mono')->copyable(),
                ])->columns(2),
            ]);
    }
}
