<?php

namespace App\Filament\Technician\Resources\ServiceRequests\Pages;

use App\Enums\ServiceRequestStatus;
use App\Filament\Technician\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
            Section::make('Detail Pekerjaan')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('serviceTemplate.name')->label('Template')->placeholder('-'),
                ]),
                TextEntry::make('title')->label('Judul'),
                TextEntry::make('description')->label('Deskripsi / Keluhan')->placeholder('-'),
                Grid::make(2)->schema([
                    TextEntry::make('scheduled_at')->label('Jadwal Kunjungan')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('warranty_expires_at')->label('Garansi Berakhir')->dateTime('d M Y H:i')->placeholder('-'),
                ]),
            ]),

            Section::make('Kondisi Sebelum Perbaikan')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('started_at')->label('Mulai Dikerjakan')->dateTime('d M Y H:i')->placeholder('-'),
                    ]),
                    TextEntry::make('before_notes')->label('Catatan Sebelum')->placeholder('-'),
                    ImageEntry::make('before_photo')->label('Foto Sebelum')->disk('public')->size(300)->default(null),
                ])
                ->visible(fn (): bool => $this->record->before_photo !== null || $this->record->before_notes !== null),

            Section::make('Kondisi Setelah Perbaikan')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('completed_at')->label('Selesai Dikerjakan')->dateTime('d M Y H:i')->placeholder('-'),
                    ]),
                    TextEntry::make('after_notes')->label('Catatan Setelah')->placeholder('-'),
                    ImageEntry::make('after_photo')->label('Foto Setelah')->disk('public')->size(300)->default(null),
                ])
                ->visible(fn (): bool => $this->record->after_photo !== null || $this->record->after_notes !== null),
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
                        ->image()->disk('public')->directory('service-requests/before')
                        ->imageEditor()->required(),

                    Textarea::make('notes')
                        ->label('Catatan Kondisi Sebelum')
                        ->rows(4)->required()
                        ->placeholder('Deskripsikan kondisi perangkat sebelum diperbaiki...'),
                ])
                ->modalHeading('Mulai Kerjakan — Kondisi Sebelum')
                ->modalDescription('Isi kondisi perangkat sebelum perbaikan dimulai.')
                ->modalSubmitActionLabel('Mulai Kerjakan')
                ->action(function (ServiceRequest $record, array $data): void {
                    $record->update([
                        'status' => ServiceRequestStatus::InProgress,
                        'before_photo' => $data['photo'],
                        'before_notes' => $data['notes'],
                        'started_at' => now(),
                    ]);
                    $this->record->refresh();

                    Notification::make()->title('Pekerjaan dimulai')->warning()->send();
                }),

            Action::make('selesai_kerjakan')
                ->label('Selesai Kerjakan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::InProgress)
                ->form([
                    FileUpload::make('photo')
                        ->label('Foto Kondisi Setelah')
                        ->image()->disk('public')->directory('service-requests/after')
                        ->imageEditor()->required(),

                    Textarea::make('notes')
                        ->label('Catatan Hasil Perbaikan')
                        ->rows(4)->required()
                        ->placeholder('Deskripsikan tindakan perbaikan dan kondisi akhir perangkat...'),
                ])
                ->modalHeading('Selesai Kerjakan — Kondisi Setelah')
                ->modalDescription('Pekerjaan akan masuk masa garansi 30 hari setelah ini.')
                ->modalSubmitActionLabel('Tandai Selesai')
                ->action(function (ServiceRequest $record, array $data): void {
                    $completedAt = now();
                    $record->update([
                        'status' => ServiceRequestStatus::Warranty,
                        'after_photo' => $data['photo'],
                        'after_notes' => $data['notes'],
                        'completed_at' => $completedAt,
                        'warranty_expires_at' => $completedAt->copy()->addDays(30),
                    ]);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Pekerjaan selesai! Garansi 30 hari hingga '.$completedAt->addDays(30)->format('d M Y').'.')
                        ->success()->send();
                }),
        ];
    }
}
