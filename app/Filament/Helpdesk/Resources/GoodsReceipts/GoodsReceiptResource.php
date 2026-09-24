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
use Illuminate\Database\Eloquent\Builder;

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

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', GoodsReceipt::class) ?? false;
    }

    public static function canView($record): bool
    {
        return $record instanceof GoodsReceipt
            && (auth()->user()?->can('view', $record) ?? false);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || ! $user->canAccessAllBranches()) {
            $branchIds = $user?->accessibleBranchIds() ?? collect();
            $query->where(function (Builder $branchQuery) use ($branchIds): void {
                $branchQuery->whereIn('local_branch_id', $branchIds)
                    ->orWhereExists(function ($mappingQuery) use ($branchIds): void {
                        $mappingQuery->selectRaw('1')
                            ->from('branch_esb_codes')
                            ->whereColumn('branch_esb_codes.esb_comcode', 'goods_receipts.company_code')
                            ->whereColumn('branch_esb_codes.esb_branch_id', 'goods_receipts.esb_branch_id')
                            ->where('branch_esb_codes.is_active', true)
                            ->whereIn('branch_esb_codes.branch_id', $branchIds);
                    });
            });
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

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
                    GoodsReceipt::STATUS_SUCCEEDED => 'Berhasil', GoodsReceipt::STATUS_FAILED => 'Gagal', GoodsReceipt::STATUS_UNKNOWN => 'Perlu Rekonsiliasi', default => 'Diproses',
                })->color(fn (string $state): string => match ($state) {
                    GoodsReceipt::STATUS_SUCCEEDED => 'success', GoodsReceipt::STATUS_FAILED, GoodsReceipt::STATUS_UNKNOWN => 'danger', default => 'warning',
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
                    GoodsReceipt::STATUS_SUCCEEDED => 'Berhasil', GoodsReceipt::STATUS_FAILED => 'Gagal', GoodsReceipt::STATUS_UNKNOWN => 'Perlu Rekonsiliasi', default => 'Diproses',
                })->color(fn (string $state): string => match ($state) {
                    GoodsReceipt::STATUS_SUCCEEDED => 'success', GoodsReceipt::STATUS_FAILED, GoodsReceipt::STATUS_UNKNOWN => 'danger', default => 'warning',
                }),
                TextColumn::make('submittedBy.name')->label('Dikirim oleh'),
                TextColumn::make('submitted_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    GoodsReceipt::STATUS_SUCCEEDED => 'Berhasil', GoodsReceipt::STATUS_FAILED => 'Gagal', GoodsReceipt::STATUS_UNKNOWN => 'Perlu Rekonsiliasi', GoodsReceipt::STATUS_PROCESSING => 'Diproses',
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
