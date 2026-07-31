<?php

namespace App\Filament\Helpdesk\Resources\Employees;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Helpdesk\Resources\Employees\Pages\EditEmployee;
use App\Filament\Helpdesk\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'employees';

    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Employee';

    protected static ?string $pluralModelLabel = 'Employee';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Employee')
                ->columns(2)
                ->schema([
                    TextInput::make('employee_code')
                        ->label('ID Employee')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),
                    TextInput::make('name')
                        ->label('Nama Employee')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('position')
                        ->label('Posisi Employee')
                        ->required()
                        ->maxLength(255),
                    Select::make('branch_id')
                        ->label('Branch')
                        ->relationship('branch', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('is_active')
                        ->label('Status')
                        ->options([1 => 'Aktif', 0 => 'Tidak Aktif'])
                        ->default(1)
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('employee_code')->label('ID Employee')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama Employee')->searchable()->sortable(),
                TextColumn::make('position')->label('Posisi')->searchable()->sortable(),
                TextColumn::make('branch.name')->label('Branch')->searchable()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([1 => 'Aktif', 0 => 'Tidak Aktif']),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()->iconButton()->tooltip('Hapus')->requiresConfirmation(),
            ])
            ->toolbarActions([
                Action::make('create')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Tambah Employee')
                    ->visible(fn () => static::canCreate())
                    ->url(static::getUrl('create')),
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
