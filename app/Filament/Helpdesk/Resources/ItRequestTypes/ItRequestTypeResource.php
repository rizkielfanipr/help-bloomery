<?php

namespace App\Filament\Helpdesk\Resources\ItRequestTypes;

use App\Filament\Exports\ItRequestTypeExporter;
use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\ItRequestTypes\Pages\CreateItRequestType;
use App\Filament\Helpdesk\Resources\ItRequestTypes\Pages\EditItRequestType;
use App\Filament\Helpdesk\Resources\ItRequestTypes\Pages\ListItRequestTypes;
use App\Models\ItRequestType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
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
                Select::make('priority')
                    ->label('Priority')
                    ->helperText('Tiket baru dengan Request Type ini otomatis memakai priority ini.')
                    ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'])
                    ->default('medium')
                    ->required(),
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
                TextColumn::make('name')->label('NAME')->searchable()->sortable(),
                TextColumn::make('priority')
                    ->label('PRIORITY')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'low' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('requests_count')->label('REQUESTS')->counts('requests')->badge(),
                IconColumn::make('is_active')->label('ACTIVE')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Edit'),
                DeleteAction::make()->iconButton()->tooltip('Hapus'),
            ])
            ->toolbarActions([
                ExportAction::make()->icon('heroicon-o-arrow-down-tray')->color('success')->iconButton()->tooltip('Export Excel')->exporter(ItRequestTypeExporter::class),

                DeleteBulkAction::make()
                    ->iconButton()
                    ->tooltip('Hapus data terpilih')
                    ->color('danger'),
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
