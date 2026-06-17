<?php

namespace App\Filament\Technician\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Technician\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestRepair;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
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

    protected static string $layout = 'filament.technician.layouts.bare';

    protected string $view = 'filament.technician.resources.service-requests.pages.view-service-request';

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
                Grid::make(2)->schema([
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('department.name')->label('Divisi Pemohon')->placeholder('-'),
                ]),
                Grid::make(2)->schema([
                    TextEntry::make('scheduledBy.name')->label('Dijadwalkan Oleh'),
                    TextEntry::make('scheduled_date')->label('Tanggal Penjadwalan')->date('d M Y'),
                ]),
                Grid::make(2)->schema([
                    TextEntry::make('technician.name')->label('Teknisi')->placeholder('Belum ditugaskan'),
                    TextEntry::make('warranty_expires_at')->label('Garansi Berakhir')->dateTime('d M Y H:i')->placeholder('-'),
                ]),
                TextEntry::make('requestor_notes')->label('Catatan Pemohon')->placeholder('-'),
            ]),

            Section::make('Lampiran')
                ->schema([
                    ImageEntry::make('attachments')
                        ->label('Lampiran')
                        ->disk('public')
                        ->size(200)
                        ->default(null),
                ])
                ->visible(fn (): bool => ! empty($this->record->attachments)),

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
                                ->disk('public')
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
                                ->disk('public')
                                ->size(280)
                                ->default(null)
                                ->visible(fn (ServiceRequestRepair $record): bool => $record->after_photo !== null),
                        ])
                        ->contained(false),
                ])
                ->visible(fn (): bool => $this->record->repairs->isNotEmpty()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mulai_kerjakan')
                ->label('Mulai Kerjakan')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::Submitted)
                ->form([
                    FileUpload::make('photo')
                        ->label('Foto Kondisi Sebelum')
                        ->image()
                        ->disk('public')
                        ->directory('service-requests/before')
                        ->imageEditor()
                        ->required(),

                    Textarea::make('notes')
                        ->label('Catatan Kondisi Sebelum')
                        ->rows(4)
                        ->required()
                        ->placeholder('Deskripsikan kondisi perangkat sebelum diperbaiki...'),
                ])
                ->modalHeading('Mulai Kerjakan — Kondisi Sebelum')
                ->modalDescription('Isi kondisi perangkat sebelum perbaikan dimulai.')
                ->modalSubmitActionLabel('Mulai Kerjakan')
                ->action(function (ServiceRequest $record, array $data): void {
                    $nextCycle = $record->repairs()->max('cycle') + 1;

                    ServiceRequestRepair::create([
                        'service_request_id' => $record->id,
                        'technician_id' => auth()->id(),
                        'cycle' => $nextCycle,
                        'before_photo' => $data['photo'],
                        'before_notes' => $data['notes'],
                        'started_at' => now(),
                    ]);

                    $record->update([
                        'status' => ServiceRequestStatus::InProgress,
                        'technician_id' => auth()->id(),
                    ]);

                    $this->record->refresh();

                    Notification::make()->title('Pekerjaan dimulai')->warning()->send();
                }),

            Action::make('selesai_kerjakan')
                ->label('Selesai Kerjakan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::InProgress
                    && $record->technician_id === auth()->id())
                ->form([
                    FileUpload::make('photo')
                        ->label('Foto Kondisi Setelah')
                        ->image()
                        ->disk('public')
                        ->directory('service-requests/after')
                        ->imageEditor()
                        ->required(),

                    Textarea::make('notes')
                        ->label('Catatan Hasil Perbaikan')
                        ->rows(4)
                        ->required()
                        ->placeholder('Deskripsikan tindakan perbaikan dan kondisi akhir perangkat...'),
                ])
                ->modalHeading('Selesai Kerjakan — Kondisi Setelah')
                ->modalDescription('Pekerjaan akan masuk masa garansi 30 hari setelah ini.')
                ->modalSubmitActionLabel('Tandai Selesai')
                ->action(function (ServiceRequest $record, array $data): void {
                    $completedAt = now();
                    $warrantyExpiresAt = $completedAt->copy()->addDays(30);

                    $record->activeRepair()->update([
                        'after_photo' => $data['photo'],
                        'after_notes' => $data['notes'],
                        'completed_at' => $completedAt,
                        'warranty_expires_at' => $warrantyExpiresAt,
                    ]);

                    $record->update([
                        'status' => ServiceRequestStatus::Warranty,
                        'warranty_expires_at' => $warrantyExpiresAt,
                    ]);

                    $this->record->refresh();

                    Notification::make()
                        ->title('Pekerjaan selesai! Garansi 30 hari hingga '.$warrantyExpiresAt->format('d M Y').'.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
