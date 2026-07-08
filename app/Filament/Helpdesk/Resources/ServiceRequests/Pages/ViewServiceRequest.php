<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Helpdesk\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestRepair;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->checkAndAutoComplete();
        $this->record->refresh();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Permintaan')->schema([
                TextEntry::make('status')->label('Status')->badge(),
                Grid::make(2)->schema([
                    TextEntry::make('scheduledBy.name')->label('Dijadwalkan Oleh'),
                    TextEntry::make('scheduled_date')->label('Tanggal Penjadwalan')->date('d M Y'),
                ]),
                Grid::make(2)->schema([
                    TextEntry::make('technician.name')->label('Teknisi')->placeholder('Belum ditugaskan'),
                    TextEntry::make('warranty_expires_at')->label('Garansi Hingga')->dateTime('d M Y H:i')->placeholder('-'),
                ]),
                TextEntry::make('requestor_notes')->label('Catatan Pemohon')->placeholder('-'),
            ]),

            Section::make('Lampiran')
                ->schema([
                    ImageEntry::make('attachments')
                        ->label('Lampiran')
                        ->disk('b2')
                        ->size(200)
                        ->default(null),
                ])
                ->visible(fn (ServiceRequest $r): bool => ! empty($r->attachments)),

            Section::make('Riwayat Perbaikan')
                ->schema([
                    RepeatableEntry::make('repairs')
                        ->label('')
                        ->schema([
                            TextEntry::make('cycle_label')
                                ->label('Tahap')
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large),

                            Grid::make(2)->schema([
                                TextEntry::make('technician.name')
                                    ->label('Teknisi')
                                    ->placeholder('-'),
                                TextEntry::make('started_at')
                                    ->label('Mulai Dikerjakan')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('-'),
                            ]),

                            TextEntry::make('before_notes')
                                ->label('Catatan Kondisi Sebelum')
                                ->placeholder('-'),

                            ImageEntry::make('before_photo')
                                ->label('Foto Kondisi Sebelum')
                                ->disk('b2')
                                ->size(280)
                                ->default(null)
                                ->visible(fn (ServiceRequestRepair $record): bool => $record->before_photo !== null),

                            Grid::make(2)->schema([
                                TextEntry::make('completed_at')
                                    ->label('Selesai Dikerjakan')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Sedang dikerjakan...'),
                                TextEntry::make('warranty_expires_at')
                                    ->label('Garansi Hingga')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('-'),
                            ]),

                            TextEntry::make('after_notes')
                                ->label('Catatan Kondisi Setelah')
                                ->placeholder('-')
                                ->visible(fn (ServiceRequestRepair $record): bool => $record->completed_at !== null),

                            ImageEntry::make('after_photo')
                                ->label('Foto Kondisi Setelah')
                                ->disk('b2')
                                ->size(280)
                                ->default(null)
                                ->visible(fn (ServiceRequestRepair $record): bool => $record->after_photo !== null),
                        ])
                        ->contained(false),
                ])
                ->visible(fn (ServiceRequest $r): bool => $r->repairs->isNotEmpty()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('klaim_garansi')
                ->label('Klaim Garansi')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Klaim Garansi')
                ->modalDescription('Pekerjaan dikembalikan ke antrian teknisi untuk perbaikan ulang. Riwayat perbaikan sebelumnya tetap tersimpan.')
                ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::Warranty)
                ->action(function (ServiceRequest $record): void {
                    $record->update([
                        'status' => ServiceRequestStatus::Submitted,
                        'technician_id' => null,
                        'warranty_expires_at' => null,
                    ]);

                    $this->record->refresh();

                    Notification::make()
                        ->title('Garansi diklaim — pekerjaan dikembalikan ke antrian teknisi')
                        ->warning()
                        ->send();
                }),
        ];
    }
}
