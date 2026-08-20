<?php

namespace App\Filament\Helpdesk\Resources\LocationTypes;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\LocationTypes\Pages\CreateLocationType;
use App\Filament\Helpdesk\Resources\LocationTypes\Pages\EditLocationType;
use App\Filament\Helpdesk\Resources\LocationTypes\Pages\ListLocationTypes;
use App\Models\LocationType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LocationTypeResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'location types';

    protected static ?string $model = LocationType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Tipe Lokasi';

    protected static ?string $modelLabel = 'Tipe Lokasi';

    protected static ?string $pluralModelLabel = 'Tipe Lokasi';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')->label('Nama Tipe')->required()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText('Contoh: Zona, Rak, Level, Bin.'),
                TextInput::make('sort_order')->label('Urutan')->numeric()->default(0)->minValue(0),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('name')->label('Nama Tipe')->searchable()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocationTypes::route('/'),
            'create' => CreateLocationType::route('/create'),
            'edit' => EditLocationType::route('/{record}/edit'),
        ];
    }
}
