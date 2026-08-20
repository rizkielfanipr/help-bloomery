<?php

namespace App\Filament\Helpdesk\Resources\QualityControlChecklistItems;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Pages\CreateQualityControlChecklistItem;
use App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Pages\EditQualityControlChecklistItem;
use App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Pages\ListQualityControlChecklistItems;
use App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Schemas\QualityControlChecklistItemForm;
use App\Filament\Helpdesk\Resources\QualityControlChecklistItems\Tables\QualityControlChecklistItemsTable;
use App\Models\QualityControlChecklistItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QualityControlChecklistItemResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'quality control checklists';

    protected static ?string $model = QualityControlChecklistItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Quality Control';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Poin Checklist QC';

    protected static ?string $pluralModelLabel = 'Master Checklist QC';

    public static function form(Schema $schema): Schema
    {
        return QualityControlChecklistItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QualityControlChecklistItemsTable::configure($table);
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
            'index' => ListQualityControlChecklistItems::route('/'),
            'create' => CreateQualityControlChecklistItem::route('/create'),
            'edit' => EditQualityControlChecklistItem::route('/{record}/edit'),
        ];
    }
}
