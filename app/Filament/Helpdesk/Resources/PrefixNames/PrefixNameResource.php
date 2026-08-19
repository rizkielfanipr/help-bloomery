<?php

namespace App\Filament\Helpdesk\Resources\PrefixNames;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\PrefixNames\Pages\CreatePrefixName;
use App\Filament\Helpdesk\Resources\PrefixNames\Pages\EditPrefixName;
use App\Filament\Helpdesk\Resources\PrefixNames\Pages\ListPrefixNames;
use App\Models\PrefixName;
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

class PrefixNameResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'prefix names';

    protected static ?string $model = PrefixName::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Prefix Name';

    protected static ?string $modelLabel = 'Prefix Name';

    protected static ?string $pluralModelLabel = 'Prefix Names';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('code')->label('Code')->required()->maxLength(50)->unique(ignoreRecord: true)
                    ->helperText('Teks yang ditempel di depan nama product, misalnya "WIP |".'),
                TextInput::make('label')->label('Label')->required()->maxLength(100)
                    ->helperText('Teks yang tampil di pilihan dropdown, misalnya "Kitchen - WIP |".'),
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
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('label')->label('Label')->searchable()->sortable(),
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
            'index' => ListPrefixNames::route('/'),
            'create' => CreatePrefixName::route('/create'),
            'edit' => EditPrefixName::route('/{record}/edit'),
        ];
    }
}
