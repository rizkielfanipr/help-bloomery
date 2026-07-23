<?php

namespace App\Filament\Helpdesk\Resources\ItRequestTypes;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\ItRequestTypes\Pages\CreateItRequestType;
use App\Filament\Helpdesk\Resources\ItRequestTypes\Pages\EditItRequestType;
use App\Filament\Helpdesk\Resources\ItRequestTypes\Pages\ListItRequestTypes;
use App\Models\ItRequestType;
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

class ItRequestTypeResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'it request types';

    protected static ?string $model = ItRequestType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Information Technology';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Request Type';

    protected static ?string $pluralModelLabel = 'Request Types';

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
                TextColumn::make('requests_count')->label('Requests')->counts('requests')->badge(),
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
            'index' => ListItRequestTypes::route('/'),
            'create' => CreateItRequestType::route('/create'),
            'edit' => EditItRequestType::route('/{record}/edit'),
        ];
    }
}
