<?php

namespace App\Filament\Helpdesk\Resources\Branches;

use App\Filament\Exports\BranchExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\Branches\Pages\CreateBranch;
use App\Filament\Helpdesk\Resources\Branches\Pages\EditBranch;
use App\Filament\Helpdesk\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'branches';

    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Branch';

    protected static ?string $pluralModelLabel = 'Branch';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Branch')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Branch')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('address')
                            ->label('Alamat')
                            ->nullable()
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),

                        Toggle::make('location_required')
                            ->label('Wajib Validasi Lokasi')
                            ->helperText('Jika aktif, karyawan wajib berada di dalam radius titik absen saat clock in/out')
                            ->default(false),
                    ])
                    ->columns(2),

                Section::make('Titik Lokasi Absen')
                    ->description('Tentukan koordinat dan radius titik absen untuk branch ini. Kosongkan jika tidak perlu validasi lokasi.')
                    ->schema([
                        Hidden::make('lat'),
                        Hidden::make('lng'),
                        Hidden::make('radius_meters')->default(100),

                        View::make('filament.schemas.components.branch-location-picker')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->paginationPageOptions([20, 50, 100])
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('address')
                    ->label('Alamat')
                    ->placeholder('-')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('lat')
                    ->label('Latitude')
                    ->placeholder('—')
                    ->numeric(7),

                TextColumn::make('lng')
                    ->label('Longitude')
                    ->placeholder('—')
                    ->numeric(7),

                TextColumn::make('radius_meters')
                    ->label('Radius (m)')
                    ->suffix(' m'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                IconColumn::make('location_required')
                    ->label('Wajib Lokasi')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
                Action::make('toggle_active')
                    ->iconButton()
                    ->icon(fn (Branch $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Branch $record): string => $record->is_active ? 'warning' : 'success')
                    ->tooltip(fn (Branch $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->requiresConfirmation()
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalHeading(fn (Branch $record): string => $record->is_active ? 'Nonaktifkan Branch?' : 'Aktifkan Branch?')
                    ->modalDescription(fn (Branch $record): string => $record->is_active
                        ? 'Branch ini akan dinonaktifkan. Data tetap tersimpan dan bisa diaktifkan kembali.'
                        : 'Branch ini akan diaktifkan kembali.')
                    ->action(function (Branch $record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Branch diaktifkan' : 'Branch dinonaktifkan')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Tambah Branch')
                    ->visible(fn () => static::canCreate())
                    ->url(static::getUrl('create')),

                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(BranchExporter::class),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
