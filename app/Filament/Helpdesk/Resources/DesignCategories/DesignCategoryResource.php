<?php

namespace App\Filament\Helpdesk\Resources\DesignCategories;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\DesignCategories\Pages\CreateDesignCategory;
use App\Filament\Helpdesk\Resources\DesignCategories\Pages\EditDesignCategory;
use App\Filament\Helpdesk\Resources\DesignCategories\Pages\ListDesignCategories;
use App\Models\DesignCategory;
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

class DesignCategoryResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'design categories';

    protected static ?string $model = DesignCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Brand Marketing';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Kategori Design';

    protected static ?string $pluralModelLabel = 'Kategori Design';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(100),

                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                TextColumn::make('name')
                    ->label('Nama Kategori')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('design_requests_count')
                    ->label('Jumlah Permintaan')
                    ->counts('designRequests')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
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
            'index' => ListDesignCategories::route('/'),
            'create' => CreateDesignCategory::route('/create'),
            'edit' => EditDesignCategory::route('/{record}/edit'),
        ];
    }
}
