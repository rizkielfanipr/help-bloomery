<?php

namespace App\Filament\Helpdesk\Resources\CasualStaff;

use App\Filament\Helpdesk\Resources\CasualStaff\Pages\EditCasualStaff;
use App\Filament\Helpdesk\Resources\CasualStaff\Pages\ListCasualStaff;
use App\Filament\Helpdesk\Resources\CasualStaff\Pages\ViewCasualStaff;
use App\Filament\Helpdesk\Resources\CasualStaff\RelationManagers\RegistrationsRelationManager;
use App\Models\CasualPosition;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CasualStaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Human Resources';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Casual Staff';

    protected static ?string $pluralModelLabel = 'Casual Staff';

    protected static ?string $slug = 'casual-staff';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('casual_staff');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pribadi')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Nomor HP')
                            ->tel()
                            ->required()
                            ->maxLength(20),

                        Select::make('casual_position_id')
                            ->label('Posisi')
                            ->options(CasualPosition::pluck('name', 'id'))
                            ->nullable()
                            ->searchable(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Data Bank')
                    ->schema([
                        TextInput::make('bank_name')
                            ->label('Nama Bank')
                            ->maxLength(100),

                        TextInput::make('bank_account_number')
                            ->label('Nomor Rekening')
                            ->maxLength(50),
                    ])
                    ->columns(2),
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

                TextColumn::make('phone')
                    ->label('Nomor HP')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('bank_name')
                    ->label('Bank')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('bank_account_number')
                    ->label('No. Rekening')
                    ->placeholder('—')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Daftar')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('Lihat'),
                EditAction::make()->iconButton()->tooltip('Edit'),
            ])
            ->toolbarActions([
                Action::make('export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Export Excel')
                    ->url(route('helpdesk.exports.casual-staff'))
                    ->openUrlInNewTab(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasualStaff::route('/'),
            'view' => ViewCasualStaff::route('/{record}'),
            'edit' => EditCasualStaff::route('/{record}/edit'),
        ];
    }
}
