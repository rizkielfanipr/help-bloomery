<?php

namespace App\Filament\Helpdesk\Resources\ErpModules;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\ErpModules\Pages\CreateErpModule;
use App\Filament\Helpdesk\Resources\ErpModules\Pages\EditErpModule;
use App\Filament\Helpdesk\Resources\ErpModules\Pages\ListErpModules;
use App\Models\ErpModule;
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

class ErpModuleResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'erp modules';

    protected static ?string $model = ErpModule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'Information Technology';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Modul ERP';

    protected static ?string $pluralModelLabel = 'Modul ERP';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Nama Modul')
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
                    ->label('Nama Modul')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('erp_repair_requests_count')
                    ->label('Jumlah Request')
                    ->counts('erpRepairRequests')
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
            'index' => ListErpModules::route('/'),
            'create' => CreateErpModule::route('/create'),
            'edit' => EditErpModule::route('/{record}/edit'),
        ];
    }
}
