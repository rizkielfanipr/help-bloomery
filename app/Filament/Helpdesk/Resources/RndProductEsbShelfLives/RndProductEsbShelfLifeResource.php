<?php

namespace App\Filament\Helpdesk\Resources\RndProductEsbShelfLives;

use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages\CreateRndProductEsbShelfLife;
use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages\EditRndProductEsbShelfLife;
use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Pages\ListRndProductEsbShelfLives;
use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Schemas\RndProductEsbShelfLifeForm;
use App\Filament\Helpdesk\Resources\RndProductEsbShelfLives\Tables\RndProductEsbShelfLivesTable;
use App\Models\RndProductEsbShelfLife;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Local Shelf Life master (docs/rnd-internal-memo-prd.md §11). A single permission,
 * `manage rnd product shelf life`, governs every action here per PRD §5.2.
 */
class RndProductEsbShelfLifeResource extends Resource
{
    protected static ?string $model = RndProductEsbShelfLife::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'Master Shelf Life';

    protected static ?string $modelLabel = 'Shelf Life';

    protected static ?string $pluralModelLabel = 'Master Shelf Life';

    protected static ?string $slug = 'rnd-product-shelf-lives';

    public static function form(Schema $schema): Schema
    {
        return RndProductEsbShelfLifeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RndProductEsbShelfLivesTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return static::hasManagePermission();
    }

    public static function canCreate(): bool
    {
        return static::hasManagePermission();
    }

    public static function canEdit(Model $record): bool
    {
        return static::hasManagePermission();
    }

    public static function canDelete(Model $record): bool
    {
        return static::hasManagePermission();
    }

    private static function hasManagePermission(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->can('manage rnd product shelf life') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRndProductEsbShelfLives::route('/'),
            'create' => CreateRndProductEsbShelfLife::route('/create'),
            'edit' => EditRndProductEsbShelfLife::route('/{record}/edit'),
        ];
    }
}
