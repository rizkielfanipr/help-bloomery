<?php

namespace App\Filament\Helpdesk\Resources\QualityControlItemJournals\Tables;

use App\Models\QualityControlItemJournal;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QualityControlItemJournalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item_journal_number')->label('Nomor Item Journal')->searchable()->copyable()->placeholder('Menunggu ESB'),
                TextColumn::make('journal_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('esb_comcode')->label('Company Code')->badge()->searchable(),
                TextColumn::make('esb_branch_name')->label('Cabang')->description(fn ($record): ?string => $record->esb_branch_code)->searchable(),
                TextColumn::make('location_name')->label('Lokasi')->searchable(),
                TextColumn::make('details_count')->label('Produk')->counts('details')->suffix(' item'),
                TextColumn::make('creator.name')->label('Dibuat Oleh')->searchable(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
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
                TextColumn::make('submitted_at')->label('Dikirim Pada')->dateTime('d M Y H:i')->placeholder('—')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'submitting' => 'Mengirim',
                    'unknown' => 'Perlu Rekonsiliasi',
                    'succeeded' => 'Berhasil',
                    'attachment_failed' => 'Attachment Gagal',
                    'verification_required' => 'Perlu Verifikasi',
                ]),
                SelectFilter::make('esb_comcode')->label('Company Code')->options(fn (): array => QualityControlItemJournal::query()->whereNotNull('esb_comcode')->distinct()->orderBy('esb_comcode')->pluck('esb_comcode', 'esb_comcode')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
