<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Branch;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Akses';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Pengguna';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view users') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create users') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('edit users') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete users') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('delete users') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Pengguna')
                    ->schema([
                        FileUpload::make('avatar')
                            ->label('Foto')
                            ->image()
                            ->disk('b2')
                            ->directory('avatars')
                            ->nullable(),

                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->prefix('BLO')
                            ->extraInputAttributes(['x-on:input' => '$el.value = $el.value.toUpperCase()'])
                            ->formatStateUsing(fn (?string $state): ?string => $state && str_starts_with($state, 'BLO') ? substr($state, 3) : $state)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? 'BLO'.strtoupper($state) : null)
                            ->rules(['nullable', 'regex:/^[A-Z0-9]+$/'])
                            ->rule(fn (?Model $record) => function ($attribute, $value, $fail) use ($record) {
                                if (! filled($value)) {
                                    return;
                                }
                                $full = 'BLO'.strtoupper($value);
                                $exists = User::where('username', $full)
                                    ->when($record, fn ($q) => $q->whereNot('id', $record->id))
                                    ->exists();
                                if ($exists) {
                                    $fail("Username {$full} sudah digunakan.");
                                }
                            })
                            ->validationMessages(['regex' => 'Username hanya boleh huruf kapital dan angka.'])
                            ->maxLength(47),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->nullable()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->minLength(8)
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('No. Telepon')
                            ->tel()
                            ->nullable()
                            ->maxLength(255),

                        Toggle::make('access_all_branches')
                            ->label('Akses Semua Cabang')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (bool $state, Set $set): void {
                                if ($state) {
                                    $set('branch_access_ids', []);
                                    $set('primary_branch_id', null);
                                }
                            }),

                        Select::make('branch_access_ids')
                            ->label('Akses Cabang')
                            ->multiple()
                            ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->visible(fn (Get $get): bool => ! (bool) $get('access_all_branches'))
                            ->afterStateUpdated(function (?array $state, Get $get, Set $set): void {
                                $ids = array_map('intval', $state ?? []);
                                $primary = (int) $get('primary_branch_id');

                                if (count($ids) === 1) {
                                    $set('primary_branch_id', $ids[0]);
                                } elseif ($primary && ! in_array($primary, $ids, true)) {
                                    $set('primary_branch_id', null);
                                }
                            })
                            ->required(fn (Get $get): bool => ! (bool) $get('access_all_branches')),

                        Select::make('primary_branch_id')
                            ->label('Cabang Utama / Default')
                            ->options(function (Get $get): array {
                                $ids = array_map('intval', $get('branch_access_ids') ?? []);

                                return Branch::query()
                                    ->whereIn('id', $ids)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->visible(fn (Get $get): bool => ! (bool) $get('access_all_branches') && count($get('branch_access_ids') ?? []) > 1)
                            ->required(fn (Get $get): bool => ! (bool) $get('access_all_branches') && count($get('branch_access_ids') ?? []) > 1)
                            ->helperText('Cabang default untuk Sales Report, Stock Card, Daily Briefing, dan pengajuan dari aplikasi user.'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->visibleOn('edit'),

                        Select::make('roles')
                            ->label('Role')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->placeholder('-'),

                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=7F9CF5&background=EBF4FF'),

                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->separator(','),

                TextColumn::make('branch.name')
                    ->label('Cabang Utama')
                    ->placeholder('-')
                    ->sortable()
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit')->color('info'),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
