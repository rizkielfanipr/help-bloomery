<?php

namespace App\Filament\Helpdesk\Resources\SalesRegions;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\SalesRegions\Pages\CreateSalesRegion;
use App\Filament\Helpdesk\Resources\SalesRegions\Pages\EditSalesRegion;
use App\Filament\Helpdesk\Resources\SalesRegions\Pages\ListSalesRegions;
use App\Models\SalesRegion;
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

class SalesRegionResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'rnd projects';

    protected static string $permissionGroup = 'Research & Development';

    protected static string $permissionLabel = 'Project';

    protected static ?string $model = SalesRegion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Region Penjualan';

    protected static ?string $modelLabel = 'Region Penjualan';

    protected static ?string $pluralModelLabel = 'Region Penjualan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Region')
                ->schema([
                    TextInput::make('name')->label('Nama Region')->required()->maxLength(255),
                    TextInput::make('code')->label('Kode')->required()->maxLength(30)->unique(ignoreRecord: true),
                    TextInput::make('sort_order')->label('Urutan')->numeric()->default(0)->required(),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Region')->searchable()->sortable(),
                TextColumn::make('code')->label('Kode')->badge()->searchable(),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('product_prices_count')->label('Data Harga')->counts('productPrices'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->disabled(fn (SalesRegion $record): bool => $record->productPrices()->exists())
                    ->tooltip(fn (SalesRegion $record): string => $record->productPrices()->exists() ? 'Nonaktifkan region karena sudah memiliki histori harga' : 'Hapus'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesRegions::route('/'),
            'create' => CreateSalesRegion::route('/create'),
            'edit' => EditSalesRegion::route('/{record}/edit'),
        ];
    }
}
