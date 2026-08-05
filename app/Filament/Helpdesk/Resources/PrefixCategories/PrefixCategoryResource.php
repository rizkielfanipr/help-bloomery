<?php

namespace App\Filament\Helpdesk\Resources\PrefixCategories;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\PrefixCategories\Pages\CreatePrefixCategory;
use App\Filament\Helpdesk\Resources\PrefixCategories\Pages\EditPrefixCategory;
use App\Filament\Helpdesk\Resources\PrefixCategories\Pages\ListPrefixCategories;
use App\Models\PrefixCategory;
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

class PrefixCategoryResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'prefix categories';

    protected static ?string $model = PrefixCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Prefix Category';

    protected static ?string $modelLabel = 'Prefix Category';

    protected static ?string $pluralModelLabel = 'Prefix Categories';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(100)->unique(ignoreRecord: true),
                TextInput::make('sort_order')->label('Order')->numeric()->default(0)->minValue(0),
                Toggle::make('is_active')->label('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
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
            'index' => ListPrefixCategories::route('/'),
            'create' => CreatePrefixCategory::route('/create'),
            'edit' => EditPrefixCategory::route('/{record}/edit'),
        ];
    }
}
