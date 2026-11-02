<?php

namespace App\Filament\Helpdesk\Resources\NonPoReceipts;

use App\Enums\NonPoReceiptStatus;
use App\Filament\Helpdesk\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Helpdesk\Resources\NonPoReceipts\Pages\ListNonPoReceipts;
use App\Filament\Helpdesk\Resources\NonPoReceipts\Pages\ViewNonPoReceipt;
use App\Models\NonPoReceipt;
use App\Models\NonPoReceiptItem;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class NonPoReceiptResource extends Resource
{
    protected static ?string $model = NonPoReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'PO Susulan';

    protected static ?string $modelLabel = 'PO Susulan';

    protected static ?string $pluralModelLabel = 'Permintaan PO Susulan dari Receiving';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', NonPoReceipt::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof NonPoReceipt && (auth()->user()?->can('view', $record) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Drafts still belong to Inventory and are not part of the Purchasing queue. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('status', '!=', NonPoReceiptStatus::Draft->value);
    }

    public static function pendingCount(): int
    {
        return NonPoReceipt::query()->whereIn('status', array_map(fn (NonPoReceiptStatus $status): string => $status->value, NonPoReceiptStatus::pendingForPurchasing()))->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Penerimaan')->columns(3)->schema([
                TextEntry::make('receipt_number')->label('Nomor')->copyable(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('receipt_date')->label('Tanggal Penerimaan')->date('d M Y'),
                TextEntry::make('supplier_text')->label('Supplier (Inventory)'),
                TextEntry::make('supplier_contact')->label('Kontak Supplier')->placeholder('-'),
                TextEntry::make('branch_name')->label('Cabang'),
                TextEntry::make('location_name')->label('Lokasi'),
                TextEntry::make('submittedBy.name')->label('Dikirim Oleh')->placeholder('-'),
                TextEntry::make('processedBy.name')->label('PIC Purchasing')->placeholder('Belum Diproses'),
                TextEntry::make('reason')->label('Alasan Tanpa PO')->columnSpanFull(),
                TextEntry::make('document_number')->label('Nomor Dokumen')->placeholder('-'),
                TextEntry::make('document_date')->label('Tanggal Dokumen')->date('d M Y')->placeholder('-'),
                TextEntry::make('document_notes')->label('Catatan Dokumen')->placeholder('-'),
            ]),
            Section::make('Hasil PO Dan Goods Receipt')->columns(3)->schema([
                TextEntry::make('po_number')->label('Nomor PO')->placeholder('Belum Tertaut'),
                TextEntry::make('po_supplier_name')->label('Supplier Resmi PO')->placeholder('-'),
                TextEntry::make('poLinkedBy.name')->label('Ditautkan Oleh')->placeholder('-'),
                TextEntry::make('goodsReceipt.esb_goods_receipt_number')->label('Nomor GR')->placeholder('-')
                    ->url(fn (NonPoReceipt $record): ?string => $record->goodsReceipt && GoodsReceiptResource::canView($record->goodsReceipt) ? GoodsReceiptResource::getUrl('view', ['record' => $record->goodsReceipt]) : null),
                TextEntry::make('goodsReceipt.sync_error')->label('Error GR')->placeholder('-')->color('danger')->columnSpan(2),
            ]),
            View::make('filament.helpdesk.non-po-receipts.po-comparison')
                ->viewData(fn ($livewire): array => ['comparison' => $livewire->poComparison ?? null])
                ->visible(fn ($livewire): bool => filled($livewire->poComparison ?? null))
                ->columnSpanFull(),
            Section::make('Barang Dan QC')->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('product_name')->label('Produk')->columnSpan(2),
                    TextEntry::make('product_code')->label('Kode')->placeholder('-'),
                    TextEntry::make('uom_name')->label('UOM'),
                    TextEntry::make('physical_qty')->label('Qty Fisik')->formatStateUsing(fn ($state): string => static::qty($state)),
                    TextEntry::make('accepted_qty')->label('Accepted')->formatStateUsing(fn ($state): string => static::qty($state)),
                    TextEntry::make('hold_qty')->label('Hold')->formatStateUsing(fn ($state): string => static::qty($state)),
                    TextEntry::make('rejected_qty')->label('Rejected')->formatStateUsing(fn ($state): string => static::qty($state)),
                    TextEntry::make('invoice_price')->label('Harga Invoice')->placeholder('-'),
                    TextEntry::make('qc_reason')->label('Alasan QC')->placeholder('-')->columnSpan(3)
                        ->state(fn (NonPoReceiptItem $record): ?string => $record->qc_data['rejectionReason'] ?? null ?: null),
                    ImageEntry::make('evidence_photos')->label('Foto Bukti')->disk('b2')->size(120)->placeholder('-')->columnSpanFull(),
                ]),
            ]),
            Section::make('Foto')->columns(2)->schema([
                ImageEntry::make('document_photos')->label('Foto Dokumen')->disk('b2')->size(180)->placeholder('-'),
                ImageEntry::make('goods_photos')->label('Foto Barang')->disk('b2')->size(180)->placeholder('-'),
            ]),
            Section::make('Riwayat Status')->schema([
                RepeatableEntry::make('statusHistories')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('to_status')->label('Status')->badge(),
                    TextEntry::make('actor.name')->label('Oleh')->placeholder('Sistem'),
                    TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                    TextEntry::make('notes')->label('Catatan')->placeholder('-'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('receipt_number')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['processedBy:id,name'])->withCount('items')->withSum('items', 'accepted_qty'))
            ->columns([
                TextColumn::make('receipt_number')->label('Nomor')->searchable()->copyable(),
                TextColumn::make('receipt_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('supplier_text')->label('Supplier')->searchable()->wrap(),
                TextColumn::make('branch_name')->label('Cabang')->searchable()->wrap(),
                TextColumn::make('items_count')->label('Item')->state(fn (NonPoReceipt $record): string => $record->items_count.' item · '.static::qty($record->items_sum_accepted_qty).' Accepted'),
                TextColumn::make('document_number')->label('Dokumen')->searchable()->placeholder('-'),
                TextColumn::make('reason')->label('Alasan')->limit(40)->tooltip(fn (NonPoReceipt $record): ?string => $record->reason),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('po_number')->label('Nomor PO')->searchable()->placeholder('-'),
                TextColumn::make('submitted_at')->label('Umur')->since()->sortable(),
                TextColumn::make('processedBy.name')->label('PIC')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(NonPoReceiptStatus::cases())
                    ->reject(fn (NonPoReceiptStatus $status): bool => $status === NonPoReceiptStatus::Draft)
                    ->mapWithKeys(fn (NonPoReceiptStatus $status): array => [$status->value => $status->getLabel()])->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('submitted_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNonPoReceipts::route('/'),
            'view' => ViewNonPoReceipt::route('/{record}'),
        ];
    }

    public static function qty(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
