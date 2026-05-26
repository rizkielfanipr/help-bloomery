<?php

namespace App\Filament\Helpdesk\Resources\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\CreateServiceRequest;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\EditServiceRequest;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Helpdesk\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Models\ServiceRequest;
use App\Models\ServiceTemplate;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
            Section::make('Detail Pekerjaan')->schema([
                Select::make('service_template_id')
                    ->label('Template Pekerjaan')
                    ->options(ServiceTemplate::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if ($state) {
                            $template = ServiceTemplate::find($state);
                            if ($template) {
                                $set('title', $template->name);
                                $set('description', $template->description);
                            }
                        }
                    })
                    ->helperText('Opsional — pilih untuk mengisi otomatis judul & deskripsi'),

                TextInput::make('title')
                    ->label('Judul Pekerjaan')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Deskripsi / Keluhan')
                    ->rows(4)
                    ->nullable(),
            ]),

            Section::make('Penugasan')->schema([
                Grid::make(2)->schema([
                    Select::make('technician_id')
                        ->label('Teknisi')
                        ->options(User::role('technician')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    DateTimePicker::make('scheduled_at')
                        ->label('Jadwal Kunjungan')
                        ->nullable(),
                ]),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Pekerjaan')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('serviceTemplate.name')->label('Template')->placeholder('-'),
                    TextEntry::make('status')->label('Status')->badge(),
                ]),
                TextEntry::make('title')->label('Judul'),
                TextEntry::make('description')->label('Deskripsi')->placeholder('-'),
                Grid::make(2)->schema([
                    TextEntry::make('technician.name')->label('Teknisi'),
                    TextEntry::make('scheduledBy.name')->label('Dijadwalkan Oleh'),
                    TextEntry::make('scheduled_at')->label('Jadwal')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('warranty_expires_at')->label('Garansi Hingga')->dateTime('d M Y H:i')->placeholder('-'),
                ]),
            ]),

            Section::make('Kondisi Sebelum Perbaikan')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('started_at')->label('Mulai')->dateTime('d M Y H:i')->placeholder('-'),
                    ]),
                    TextEntry::make('before_notes')->label('Catatan Sebelum')->placeholder('-'),
                    ImageEntry::make('before_photo')->label('Foto Sebelum')->disk('public')->size(300)->default(null),
                ])
                ->visible(fn (ServiceRequest $r): bool => $r->before_photo !== null || $r->before_notes !== null),

            Section::make('Kondisi Setelah Perbaikan')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('-'),
                    ]),
                    TextEntry::make('after_notes')->label('Catatan Setelah')->placeholder('-'),
                    ImageEntry::make('after_photo')->label('Foto Setelah')->disk('public')->size(300)->default(null),
                ])
                ->visible(fn (ServiceRequest $r): bool => $r->after_photo !== null || $r->after_notes !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Pekerjaan')->searchable()->limit(40),
                TextColumn::make('serviceTemplate.name')->label('Template')->placeholder('-'),
                TextColumn::make('technician.name')->label('Teknisi')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('scheduled_at')->label('Jadwal')->dateTime('d M Y H:i')->sortable()->placeholder('-'),
                TextColumn::make('warranty_expires_at')->label('Garansi Hingga')->dateTime('d M Y')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ServiceRequestStatus::class),
                SelectFilter::make('technician_id')->label('Teknisi')
                    ->options(User::role('technician')->pluck('name', 'id'))->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
        return parent::getEloquentQuery()->with(['serviceTemplate', 'technician', 'scheduledBy']);
    }
}
