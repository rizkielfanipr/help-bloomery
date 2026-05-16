<?php

namespace App\Filament\Technician\Resources\MaintenanceReports;

use App\Enums\MaintenanceStatus;
use App\Filament\Technician\Resources\MaintenanceReports\Pages\CreateMaintenanceReport;
use App\Filament\Technician\Resources\MaintenanceReports\Pages\EditMaintenanceReport;
use App\Filament\Technician\Resources\MaintenanceReports\Pages\ListMaintenanceReports;
use App\Models\MaintenanceReport;
use App\Models\MaintenanceSchedule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceReportResource extends Resource
{
    protected static ?string $model = MaintenanceReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'technician']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Jadwal Maintenance')
                    ->schema([
                        Select::make('maintenance_schedule_id')
                            ->label('Jadwal Maintenance')
                            ->options(
                                MaintenanceSchedule::whereIn('status', [
                                    MaintenanceStatus::Scheduled->value,
                                    MaintenanceStatus::InProgress->value,
                                ])
                                    ->with('erpRepairRequest')
                                    ->get()
                                    ->pluck('erpRepairRequest.catatan_perbaikan', 'id')
                            )
                            ->searchable()
                            ->required(),

                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('started_at')
                                    ->label('Waktu Mulai'),

                                DateTimePicker::make('completed_at')
                                    ->label('Waktu Selesai'),
                            ]),
                    ]),

                Section::make('Kondisi Sebelum')
                    ->schema([
                        Textarea::make('kondisi_sebelum')
                            ->label('Kondisi Sebelum Perbaikan')
                            ->required()
                            ->rows(4),

                        FileUpload::make('before_photos')
                            ->label('Foto Sebelum')
                            ->multiple()
                            ->disk('public')
                            ->directory('maintenance/before')
                            ->acceptedFileTypes(['image/*'])
                            ->maxSize(5120)
                            ->nullable(),
                    ]),

                Section::make('Kondisi Sesudah')
                    ->schema([
                        Textarea::make('kondisi_sesudah')
                            ->label('Kondisi Sesudah Perbaikan')
                            ->required()
                            ->rows(4),

                        FileUpload::make('after_photos')
                            ->label('Foto Sesudah')
                            ->multiple()
                            ->disk('public')
                            ->directory('maintenance/after')
                            ->acceptedFileTypes(['image/*'])
                            ->maxSize(5120)
                            ->nullable(),
                    ]),

                Section::make('Tindakan')
                    ->schema([
                        Textarea::make('tindakan_perbaikan')
                            ->label('Tindakan Perbaikan yang Dilakukan')
                            ->required()
                            ->rows(5),
                    ]),

                Hidden::make('technician_id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('maintenanceSchedule.erpRepairRequest.catatan_perbaikan')
                    ->label('Permintaan ERP')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('technician.name')
                    ->label('Teknisi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                IconColumn::make('before_photos')
                    ->label('Foto Sebelum')
                    ->getStateUsing(fn (MaintenanceReport $record): bool => ! empty($record->before_photos))
                    ->boolean(),

                IconColumn::make('after_photos')
                    ->label('Foto Sesudah')
                    ->getStateUsing(fn (MaintenanceReport $record): bool => ! empty($record->after_photos))
                    ->boolean(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceReports::route('/'),
            'create' => CreateMaintenanceReport::route('/create'),
            'edit' => EditMaintenanceReport::route('/{record}/edit'),
        ];
    }
}
