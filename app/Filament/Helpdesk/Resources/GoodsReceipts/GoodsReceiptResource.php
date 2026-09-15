<?php

namespace App\Filament\Helpdesk\Resources\GoodsReceipts;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Helpdesk\Resources\GoodsReceipts\Pages\ViewGoodsReceipt;
use App\Models\GoodsReceipt;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GoodsReceiptResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = GoodsReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static string $permissionPrefix = 'goods receipts';

    protected static string $permissionGroup = 'Inventory';

    protected static string $permissionLabel = 'Penerimaan';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Receiving';

    protected static ?string $modelLabel = 'Penerimaan';

    protected static ?string $pluralModelLabel = 'Penerimaan';

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference_number')->label('Nomor PO'),
                TextEntry::make('esb_goods_receipt_number')->label('Nomor GR')->placeholder('-'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    GoodsReceipt::STATUS_SUCCEEDED => 'Berhasil', GoodsReceipt::STATUS_FAILED => 'Gagal', default => 'Diproses',
                })->color(fn (string $state): string => match ($state) {
                    GoodsReceipt::STATUS_SUCCEEDED => 'success', GoodsReceipt::STATUS_FAILED => 'danger', default => 'warning',
                }),
                TextEntry::make('goods_receipt_date')->label('Tanggal')->date('d M Y'),
                TextEntry::make('supplier_name')->label('Supplier'),
                TextEntry::make('branch_name')->label('Cabang'),
                TextEntry::make('location_name')->label('Lokasi'),
                TextEntry::make('delivery_number')->label('Surat Jalan')->placeholder('-'),
                TextEntry::make('submittedBy.name')->label('Dikirim oleh'),
                TextEntry::make('esb_message')->label('Respons ESB')->placeholder('-')->columnSpanFull(),
                TextEntry::make('sync_error')->label('Error')->placeholder('-')->color('danger')->columnSpanFull(),
                RepeatableEntry::make('items')->label('Barang')->schema([
                    TextEntry::make('product_name')->label('Produk'),
                    TextEntry::make('product_code')->label('Kode')->placeholder('-'),
                    TextEntry::make('received_qty')->label('Qty')->formatStateUsing(fn ($state, $record): string => rtrim(rtrim(number_format((float) $state, 4, '.', ''), '0'), '.').' '.$record->uom_name),
                    TextEntry::make('notes')->label('Catatan')->placeholder('-'),
                ])->columns(4)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_number')
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Nomor PO')
                    ->searchable(),
                TextColumn::make('esb_goods_receipt_number')->label('Nomor GR')->searchable()->placeholder('-'),
                TextColumn::make('goods_receipt_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('supplier_name')->label('Supplier')->searchable(),
                TextColumn::make('branch_name')->label('Cabang')->searchable(),
                TextColumn::make('location_name')->label('Lokasi'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    GoodsReceipt::STATUS_SUCCEEDED => 'Berhasil', GoodsReceipt::STATUS_FAILED => 'Gagal', default => 'Diproses',
                })->color(fn (string $state): string => match ($state) {
                    GoodsReceipt::STATUS_SUCCEEDED => 'success', GoodsReceipt::STATUS_FAILED => 'danger', default => 'warning',
                }),
                TextColumn::make('submittedBy.name')->label('Dikirim oleh'),
                TextColumn::make('submitted_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    GoodsReceipt::STATUS_SUCCEEDED => 'Berhasil', GoodsReceipt::STATUS_FAILED => 'Gagal', GoodsReceipt::STATUS_PROCESSING => 'Diproses',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])->defaultSort('submitted_at', 'desc');
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
            'index' => ListGoodsReceipts::route('/'),
            'view' => ViewGoodsReceipt::route('/{record}'),
        ];
    }
}
