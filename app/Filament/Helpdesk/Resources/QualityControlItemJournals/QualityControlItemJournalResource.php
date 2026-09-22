<?php

namespace App\Filament\Helpdesk\Resources\QualityControlItemJournals;

use App\Filament\Helpdesk\Resources\QualityControlItemJournals\Pages\ListQualityControlItemJournals;
use App\Filament\Helpdesk\Resources\QualityControlItemJournals\Pages\ViewQualityControlItemJournal;
use App\Filament\Helpdesk\Resources\QualityControlItemJournals\Schemas\QualityControlItemJournalInfolist;
use App\Filament\Helpdesk\Resources\QualityControlItemJournals\Tables\QualityControlItemJournalsTable;
use App\Models\QualityControlItemJournal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QualityControlItemJournalResource extends Resource
{
    protected static ?string $model = QualityControlItemJournal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Quality Control';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Item Journal';

    protected static ?string $modelLabel = 'Item Journal';

    protected static ?string $pluralModelLabel = 'Item Journal';

    protected static ?string $recordTitleAttribute = 'item_journal_number';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', QualityControlItemJournal::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof QualityControlItemJournal
            && (auth()->user()?->can('view', $record) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['creator', 'details', 'attachments']);
        $user = auth()->user();

        return $user ? $query->visibleTo($user) : $query->whereRaw('1 = 0');
    }

    public static function infolist(Schema $schema): Schema
    {
        return QualityControlItemJournalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QualityControlItemJournalsTable::configure($table);
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
            'index' => ListQualityControlItemJournals::route('/'),
            'view' => ViewQualityControlItemJournal::route('/{record}'),
        ];
    }
}
