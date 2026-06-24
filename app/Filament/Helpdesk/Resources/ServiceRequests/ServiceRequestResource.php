<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\CreateServiceRequest;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\EditServiceRequest;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestRepair;
use App\Models\TechnicianSettings;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Teknisi';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Permintaan Servis';

    protected static ?string $pluralModelLabel = 'Permintaan Servis';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Permintaan')->schema([
                DatePicker::make('scheduled_date')
                    ->label('Tanggal Penjadwalan')
                    ->required()
                    ->minDate(today())
                    ->live()
                    ->helperText(function ($state): string {
                        if (! $state) {
                            return 'Pilih tanggal untuk melihat ketersediaan slot.';
                        }

                        $max = TechnicianSettings::instance()->max_jobs_per_day;
                        $booked = ServiceRequest::whereDate('scheduled_date', $state)->count();
                        $remaining = max(0, $max - $booked);

                        if ($remaining === 0) {
                            return "⛔ Kuota penuh untuk tanggal ini ({$booked}/{$max} pekerjaan). Pilih tanggal lain.";
                        }

                        return "✅ {$remaining} slot tersisa dari {$max} untuk tanggal ini.";
                    }),

                Textarea::make('requestor_notes')
                    ->label('Catatan Pemohon')
                    ->rows(4)
                    ->nullable()
                    ->placeholder('Deskripsikan kebutuhan / keluhan secara singkat...'),

                FileUpload::make('attachments')
                    ->label('Lampiran')
                    ->multiple()
                    ->disk('public')
                    ->directory('service-requests/attachments')
                    ->nullable()
                    ->helperText('Upload foto, dokumen, atau file pendukung lainnya.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
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
                        ->disk('public')
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
                ->visible(fn (ServiceRequest $r): bool => $r->repairs->isNotEmpty()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('scheduledBy.name')->label('Pemohon')->searchable(),
                TextColumn::make('technician.name')->label('Teknisi')->placeholder('Belum ditugaskan')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('warranty_expires_at')->label('Garansi Hingga')->dateTime('d M Y')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ServiceRequestStatus::class),
                SelectFilter::make('technician_id')->label('Teknisi')
                    ->options(User::role('technician')->pluck('name', 'id'))->searchable(),
            ])
            ->defaultSort('scheduled_date', 'desc')
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat')->color('info'),
                EditAction::make()->iconButton()->tooltip('Edit')->color('warning'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->color('danger'),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Tambah Permintaan')
                    ->url(static::getUrl('create')),

                Action::make('import_excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Import Excel')
                    ->action(function (): void {
                        Notification::make()
                            ->title('Segera hadir')
                            ->body('Fitur Import Excel belum tersedia.')
                            ->warning()
                            ->send();
                    }),

                Action::make('export_excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Export Excel')
                    ->action(function (): void {
                        Notification::make()
                            ->title('Segera hadir')
                            ->body('Fitur Export Excel belum tersedia.')
                            ->warning()
                            ->send();
                    }),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRequests::route('/'),
            'create' => CreateServiceRequest::route('/create'),
            'edit' => EditServiceRequest::route('/{record}/edit'),
            'view' => ViewServiceRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['technician', 'scheduledBy', 'repairs.technician']);
    }
}
