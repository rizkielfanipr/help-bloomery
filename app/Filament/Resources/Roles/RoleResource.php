<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Forms\Components\TemplatePermissionsMatrix;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Akses';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Role';
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('super_admin');
    }

    public static function form(Schema $schema): Schema
    {
        $groupConfig = config('permissions', []);

        $descriptions = collect($groupConfig)
            ->flatMap(fn (array $permissions, string $group): array => array_fill_keys($permissions, $group))
            ->toArray();

        $generalPermissions = Permission::where('name', 'not like', 'helpdesk.%.%')
            ->orderBy('name')
            ->get();

        $permissionOptions = $generalPermissions
            ->mapWithKeys(fn (Permission $permission): array => [
                $permission->id => $permission->name,
            ])
            ->toArray();

        $optionDescriptions = $generalPermissions
            ->mapWithKeys(fn (Permission $permission): array => [
                $permission->id => $descriptions[$permission->name] ?? '',
            ])
            ->toArray();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Role')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Role')
                            ->required()
                            ->maxLength(255)
                            ->unique(Role::class, 'name', ignoreRecord: true),

                        TextInput::make('guard_name')
                            ->label('Guard')
                            ->default('web')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Permissions Umum')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->relationship(titleAttribute: 'name')
                            ->options($permissionOptions)
                            ->descriptions($optionDescriptions)
                            ->columns(3)
                            ->bulkToggleable()
                            ->searchable(),
                    ]),

                Section::make('Akses Template Form')
                    ->description('Atur akses CRUD per template form untuk role ini.')
                    ->schema([
                        TemplatePermissionsMatrix::make('template_permissions')
                            ->label(''),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Role')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('permissions_count')
                    ->label('Permission')
                    ->counts('permissions')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('users_count')
                    ->label('Pengguna')
                    ->counts('users')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
