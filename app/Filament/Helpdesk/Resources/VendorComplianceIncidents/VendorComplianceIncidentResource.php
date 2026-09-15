<?php

namespace App\Filament\Helpdesk\Resources\VendorComplianceIncidents;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages\EditVendorComplianceIncident;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages\ListVendorComplianceIncidents;
use App\Filament\Helpdesk\Resources\VendorComplianceIncidents\Pages\ViewVendorComplianceIncident;
use App\Models\VendorComplianceIncident;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VendorComplianceIncidentResource extends Resource
{
    use HasPermissions;

    protected static ?string $model = VendorComplianceIncident::class;

    protected static string $permissionPrefix = 'vendor compliance incidents';

    protected static string $permissionGroup = 'Purchasing';

    protected static string $permissionLabel = 'Vendor Compliance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Vendor Compliance';

    protected static ?string $modelLabel = 'Insiden Vendor';

    protected static ?string $pluralModelLabel = 'Vendor Compliance';

    protected static ?string $recordTitleAttribute = 'incident_number';

    public static function canCreate(): bool
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Insiden')->columns(2)->schema([
                TextInput::make('incident_number')->label('Nomor Insiden')->disabled(),
                TextInput::make('supplier_name')->label('Vendor')->disabled(),
                TextInput::make('goodsReceipt.reference_number')->label('Nomor PO')->disabled(),
                TextInput::make('item.product_name')->label('Produk')->disabled(),
                TextInput::make('affected_quantity')->label('Qty Rejected')->disabled(),
                TextInput::make('demerit_points')->label('Poin Demerit')->disabled(),
                Textarea::make('description')->label('Alasan Penolakan')->disabled()->columnSpanFull(),
            ]),
            Section::make('Tindak Lanjut Purchasing')->schema([
                Select::make('status')->label('Status Penanganan')->options(static::statusOptions())->required()->native(false),
                Select::make('action_type')->label('Jenis Tindak Lanjut')->options([
                    'claim' => 'Klaim Vendor', 'return' => 'Retur Barang', 'replacement' => 'Penggantian Barang',
                    'credit_note' => 'Credit Note', 'other' => 'Lainnya',
                ])->nullable()->native(false),
                Textarea::make('follow_up_notes')->label('Catatan Tindak Lanjut')->rows(4)
                    ->placeholder('Tuliskan nomor klaim/retur, respons vendor, dan hasil penyelesaian.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Insiden')->columns(3)->schema([
                TextEntry::make('incident_number')->label('Nomor Insiden')->copyable(),
                TextEntry::make('occurred_at')->label('Tanggal Kejadian')->dateTime('d M Y H:i'),
                TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => static::statusOptions()[$state] ?? $state)->color(fn (string $state): string => static::statusColor($state)),
                TextEntry::make('supplier_name')->label('Vendor'),
                TextEntry::make('goodsReceipt.reference_number')->label('Nomor PO'),
                TextEntry::make('goodsReceipt.esb_goods_receipt_number')->label('Nomor GR')->placeholder('-'),
                TextEntry::make('item.product_name')->label('Produk'),
                TextEntry::make('affected_quantity')->label('Qty Rejected')->suffix(fn (VendorComplianceIncident $record): string => ' '.($record->item?->uom_name ?? '')),
                TextEntry::make('item.quarantine_location')->label('Lokasi Quarantine')->placeholder('-'),
                TextEntry::make('category')->label('Kategori')->badge()->formatStateUsing(fn (string $state): string => static::categoryLabel($state)),
                TextEntry::make('severity')->label('Severity')->badge(),
                TextEntry::make('demerit_points')->label('Poin Demerit')->suffix(' Poin')->badge()->color('danger'),
                TextEntry::make('description')->label('Alasan Penolakan')->columnSpanFull(),
                TextEntry::make('reportedBy.name')->label('Dilaporkan Oleh')->placeholder('-'),
            ]),
            Section::make('Foto Bukti')->schema([
                ImageEntry::make('evidence_photos')->label('Lampiran QC')->disk('b2')->size(180),
            ])->visible(fn (VendorComplianceIncident $record): bool => ! empty($record->evidence_photos)),
            Section::make('Tindak Lanjut Purchasing')->columns(2)->schema([
                TextEntry::make('action_type')->label('Jenis Tindak Lanjut')->formatStateUsing(fn (?string $state): string => static::actionOptions()[$state] ?? '-'),
                TextEntry::make('handledBy.name')->label('PIC Purchasing')->placeholder('Belum Ditangani'),
                TextEntry::make('follow_up_notes')->label('Catatan Tindak Lanjut')->placeholder('-')->columnSpanFull(),
                TextEntry::make('resolved_at')->label('Diselesaikan Pada')->dateTime('d M Y H:i')->placeholder('-'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('incident_number')->columns([
            TextColumn::make('incident_number')->label('Nomor Insiden')->searchable()->copyable(),
            TextColumn::make('occurred_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('supplier_name')->label('Vendor')->searchable()->sortable(),
            TextColumn::make('goodsReceipt.reference_number')->label('Nomor PO')->searchable(),
            TextColumn::make('item.product_name')->label('Produk')->searchable(),
            TextColumn::make('category')->label('Kategori')->badge()->formatStateUsing(fn (string $state): string => static::categoryLabel($state)),
            TextColumn::make('affected_quantity')->label('Qty Rejected')->numeric(decimalPlaces: 4),
            TextColumn::make('demerit_points')->label('Demerit')->suffix(' Poin')->sortable()->summarize(Sum::make()->label('Total')),
            TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => static::statusOptions()[$state] ?? $state)->color(fn (string $state): string => static::statusColor($state)),
            TextColumn::make('handledBy.name')->label('PIC')->placeholder('-'),
        ])->filters([
            SelectFilter::make('status')->options(static::statusOptions()),
            SelectFilter::make('category')->options([
                'document' => 'Dokumen', 'quantity' => 'Quantity', 'quality' => 'Quality', 'packaging' => 'Packaging',
                'cold_chain' => 'Cold Chain', 'shelf_life' => 'Shelf Life', 'sampling' => 'Sampling', 'other' => 'Lainnya',
            ]),
        ])->recordActions([ViewAction::make(), EditAction::make()])->defaultSort('occurred_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorComplianceIncidents::route('/'),
            'view' => ViewVendorComplianceIncident::route('/{record}'),
            'edit' => EditVendorComplianceIncident::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = VendorComplianceIncident::query()->where('status', '!=', 'resolved')->count();

        return $count > 0 ? (string) $count : null;
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return ['open' => 'Open', 'in_progress' => 'Diproses', 'claim_or_return_submitted' => 'Klaim/Retur Diajukan', 'resolved' => 'Selesai'];
    }

    /** @return array<string, string> */
    private static function actionOptions(): array
    {
        return ['claim' => 'Klaim Vendor', 'return' => 'Retur Barang', 'replacement' => 'Penggantian Barang', 'credit_note' => 'Credit Note', 'other' => 'Lainnya'];
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'resolved' => 'success', 'in_progress' => 'info', 'claim_or_return_submitted' => 'warning', default => 'danger'
        };
    }

    private static function categoryLabel(string $category): string
    {
        return ['document' => 'Dokumen', 'quantity' => 'Quantity', 'quality' => 'Quality', 'packaging' => 'Packaging',
            'cold_chain' => 'Cold Chain', 'shelf_life' => 'Shelf Life', 'sampling' => 'Sampling', 'other' => 'Lainnya'][$category] ?? $category;
    }
}
