<?php

namespace App\Filament\Helpdesk\Resources\ComplimentTypes;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\ComplimentTypes\Pages\CreateComplimentType;
use App\Filament\Helpdesk\Resources\ComplimentTypes\Pages\EditComplimentType;
use App\Filament\Helpdesk\Resources\ComplimentTypes\Pages\ListComplimentTypes;
use App\Filament\Helpdesk\Resources\ComplimentTypes\Schemas\ComplimentTypeForm;
use App\Filament\Helpdesk\Resources\ComplimentTypes\Tables\ComplimentTypesTable;
use App\Models\ComplimentType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ComplimentTypeResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'compliment types';

    protected static ?string $model = ComplimentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Compliment Types';

    protected static ?string $modelLabel = 'Compliment Type';

    protected static ?string $pluralModelLabel = 'Compliment Types';

    public static function form(Schema $schema): Schema
    {
        return ComplimentTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComplimentTypesTable::configure($table);
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
            'index' => ListComplimentTypes::route('/'),
            'create' => CreateComplimentType::route('/create'),
            'edit' => EditComplimentType::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete compliment types')
            && ! $record->salesReportCompliments()->exists();
    }
}
