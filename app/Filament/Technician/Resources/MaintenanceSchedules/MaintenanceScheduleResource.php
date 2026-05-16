<?php

namespace App\Filament\Technician\Resources\MaintenanceSchedules;

use App\Enums\MaintenanceStatus;
use App\Filament\Technician\Resources\MaintenanceReports\MaintenanceReportResource;
use App\Filament\Technician\Resources\MaintenanceSchedules\Pages\CreateMaintenanceSchedule;
use App\Filament\Technician\Resources\MaintenanceSchedules\Pages\EditMaintenanceSchedule;
use App\Filament\Technician\Resources\MaintenanceSchedules\Pages\ListMaintenanceSchedules;
use App\Models\MaintenanceSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceScheduleResource extends Resource
{
    protected static ?string $model = MaintenanceSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 1;

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
                        Select::make('status')
                            ->label('Status')
                            ->options(MaintenanceStatus::class)
                            ->required(),

                        DateTimePicker::make('scheduled_at')
                            ->label('Jadwal Pelaksanaan')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('erpRepairRequest.catatan_perbaikan')
                    ->label('Permintaan ERP')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('erpRepairRequest.requester.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('scheduled_at')
                    ->label('Jadwal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('technician.name')
                    ->label('Teknisi')
                    ->default('-'),

                IconColumn::make('report')
                    ->label('Report')
                    ->exists('report')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(MaintenanceStatus::class),
            ])
            ->recordActions([
                Action::make('buat_report')
                    ->label('Buat Report')
                    ->icon('heroicon-o-document-plus')
                    ->url(fn (MaintenanceSchedule $record): string => MaintenanceReportResource::getUrl('create', ['maintenance_schedule_id' => $record->id]))
                    ->visible(fn (MaintenanceSchedule $record): bool => ! $record->report()->exists()),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('technician_id', auth()->id());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceSchedules::route('/'),
            'create' => CreateMaintenanceSchedule::route('/create'),
            'edit' => EditMaintenanceSchedule::route('/{record}/edit'),
        ];
    }
}
