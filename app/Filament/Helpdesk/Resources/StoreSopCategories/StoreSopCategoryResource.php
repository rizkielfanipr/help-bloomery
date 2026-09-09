<?php

namespace App\Filament\Helpdesk\Resources\StoreSopCategories;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\StoreSopCategories\Pages\CreateStoreSopCategory;
use App\Filament\Helpdesk\Resources\StoreSopCategories\Pages\EditStoreSopCategory;
use App\Filament\Helpdesk\Resources\StoreSopCategories\Pages\ListStoreSopCategories;
use App\Models\StoreSopCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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

class StoreSopCategoryResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'store sop categories';

    protected static ?string $model = StoreSopCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Operational';

    protected static ?string $navigationLabel = 'SOP Kategori';

    protected static ?string $modelLabel = 'Kategori SOP';

    protected static ?string $pluralModelLabel = 'Kategori SOP';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kategori SOP')->schema([
                TextInput::make('name')->label('Nama Kategori')->required()->maxLength(100)->unique(ignoreRecord: true),
                TextInput::make('sort_order')->label('Urutan')->numeric()->minValue(0)->default(0)->required(),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sort_order')->label('Urutan')->sortable(),
            TextColumn::make('name')->label('Nama Kategori')->searchable()->sortable(),
            TextColumn::make('store_sops_count')->label('Jumlah SOP')->counts('storeSops')->badge(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->defaultSort('sort_order')->recordActions([
            EditAction::make()->iconButton(),
            DeleteAction::make()->iconButton()->requiresConfirmation(),
        ])->toolbarActions([
            BulkActionGroup::make([DeleteBulkAction::make()]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreSopCategories::route('/'),
            'create' => CreateStoreSopCategory::route('/create'),
            'edit' => EditStoreSopCategory::route('/{record}/edit'),
        ];
    }
}
