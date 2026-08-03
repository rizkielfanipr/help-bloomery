<?php

namespace App\Filament\Helpdesk\Resources\FuelTypes;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\FuelTypes\Pages\CreateFuelType;
use App\Filament\Helpdesk\Resources\FuelTypes\Pages\EditFuelType;
use App\Filament\Helpdesk\Resources\FuelTypes\Pages\ListFuelTypes;
use App\Filament\Helpdesk\Resources\FuelTypes\Schemas\FuelTypeForm;
use App\Filament\Helpdesk\Resources\FuelTypes\Tables\FuelTypesTable;
use App\Models\FuelType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class FuelTypeResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'fuel types';

    protected static string $permissionGroup = 'Driver';

    protected static ?string $model = FuelType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Driver';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Jenis BBM';

    protected static ?string $pluralModelLabel = 'Jenis BBM';

    public static function form(Schema $schema): Schema
    {
        return FuelTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuelTypesTable::configure($table);
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
            'index' => ListFuelTypes::route('/'),
            'create' => CreateFuelType::route('/create'),
            'edit' => EditFuelType::route('/{record}/edit'),
        ];
    }
}
