<?php

namespace App\Filament\Helpdesk\Resources;

use App\Filament\Helpdesk\Resources\TechnicianMonthlyMaintenanceResource\Pages\CreateTechnicianMonthlyMaintenance;
use App\Filament\Helpdesk\Resources\TechnicianMonthlyMaintenanceResource\Pages\EditTechnicianMonthlyMaintenance;
use App\Filament\Helpdesk\Resources\TechnicianMonthlyMaintenanceResource\Pages\ListTechnicianMonthlyMaintenances;
use App\Models\TechnicianMaintenance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TechnicianMonthlyMaintenanceResource extends Resource
{
    protected static ?string $model = TechnicianMaintenance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Teknisi';

    public static function getNavigationLabel(): string
    {
        return 'Pemeliharaan';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view technician monthly maintenance') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create technician monthly maintenance') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('edit technician monthly maintenance') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete technician monthly maintenance') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Pemeliharaan Bulanan Teknisi')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->relationship('branch', 'name')
                            ->required(),
                        TextInput::make('maintenance_year')
                            ->label('Tahun')
                            ->required()
                            ->numeric()
                            ->minValue(2020)
                            ->maxValue(2100),
                        Select::make('maintenance_month')
                            ->label('Bulan')
                            ->required()
                            ->options([
                                1 => 'Januari',
                                2 => 'Februari',
                                3 => 'Maret',
                                4 => 'April',
                                5 => 'Mei',
                                6 => 'Juni',
                                7 => 'Juli',
                                8 => 'Agustus',
                                9 => 'September',
                                10 => 'Oktober',
                                11 => 'November',
                                12 => 'Desember',
                            ]),
                        TextInput::make('status')->label('Status')->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->searchable(),
                TextColumn::make('maintenance_year')
                    ->label('Tahun')
                    ->sortable(),
                TextColumn::make('maintenance_month')
                    ->label('Bulan')
                    ->formatStateUsing(fn (string $state): string => [
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember',
                    ][$state]),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('view_recap')
                    ->label('Lihat Rekap')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->iconButton()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->extraModalWindowAttributes(['class' => 'technician-maintenance-recap-modal'])
                    ->modalHeading('Rekap Maintenance')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (TechnicianMaintenance $record) => view('filament.helpdesk.technician-maintenance.recap', [
                        'maintenance' => $record->load(['branch', 'technician', 'items']),
                    ])),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTechnicianMonthlyMaintenances::route('/'),
            'create' => CreateTechnicianMonthlyMaintenance::route('/create'),
            'edit' => EditTechnicianMonthlyMaintenance::route('/edit/{record}'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
        // No branch restriction for backoffice
    }
}
