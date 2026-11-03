<?php

namespace App\Filament\Helpdesk\Resources\VendorComplianceIncidents;

use App\Enums\VendorComplianceLiabilityStatus;
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
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', VendorComplianceIncident::class) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof VendorComplianceIncident
            && (auth()->user()?->can('view', $record) ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof VendorComplianceIncident
            && (auth()->user()?->can('update', $record) ?? false);
    }

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
                TextInput::make('item.product_name')->label('Produk')->disabled()
                    ->visible(fn (?VendorComplianceIncident $record): bool => ! $record?->isLateDelivery()),
                TextInput::make('affected_quantity')->label('Qty Rejected')->disabled()
                    ->visible(fn (?VendorComplianceIncident $record): bool => ! $record?->isLateDelivery()),
                TextInput::make('delay_days')->label('Hari Terlambat')->disabled()->suffix('hari')
                    ->visible(fn (?VendorComplianceIncident $record): bool => (bool) $record?->isLateDelivery()),
                TextInput::make('demerit_points')->label('Poin Demerit')->disabled(),
                Textarea::make('description')->label(fn (?VendorComplianceIncident $record): string => $record?->isLateDelivery() ? 'Keterangan' : 'Alasan Penolakan')->disabled()->columnSpanFull(),
            ]),
            Section::make('Tindak Lanjut Purchasing')->schema([
                Select::make('status')->label('Status Penanganan')->required()->native(false)
                    ->options(fn (?VendorComplianceIncident $record): array => $record?->liability_status === VendorComplianceLiabilityStatus::Pending
                        ? array_diff_key(static::statusOptions(), ['resolved' => true])
                        : static::statusOptions())
                    ->helperText(fn (?VendorComplianceIncident $record): ?string => $record?->liability_status === VendorComplianceLiabilityStatus::Pending
                        ? 'Status Selesai tersedia setelah keterlambatan dikonfirmasi atau dikecualikan.'
                        : null),
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
                TextEntry::make('item.product_name')->label('Produk')->placeholder('-')
                    ->state(fn (VendorComplianceIncident $record): ?string => $record->isLateDelivery() ? 'Level PO' : $record->item?->product_name),
                TextEntry::make('affected_quantity')->label('Qty Rejected')->placeholder('-')
                    ->suffix(fn (VendorComplianceIncident $record): string => ' '.($record->item?->uom_name ?? ''))
                    ->visible(fn (VendorComplianceIncident $record): bool => ! $record->isLateDelivery()),
                TextEntry::make('item.quarantine_location')->label('Lokasi Quarantine')->placeholder('-')
                    ->visible(fn (VendorComplianceIncident $record): bool => ! $record->isLateDelivery()),
                TextEntry::make('category')->label('Kategori')->badge()->formatStateUsing(fn (string $state): string => static::categoryLabel($state)),
                TextEntry::make('severity')->label('Severity')->badge(),
                TextEntry::make('demerit_points')->label('Poin Demerit')->suffix(' Poin')->badge()->color('danger'),
                TextEntry::make('description')->label(fn (VendorComplianceIncident $record): string => $record->isLateDelivery() ? 'Keterangan' : 'Alasan Penolakan')->columnSpanFull(),
                TextEntry::make('reportedBy.name')->label('Dilaporkan Oleh')->placeholder('-'),
            ]),
            Section::make('Informasi Keterlambatan')->columns(3)
                ->visible(fn (VendorComplianceIncident $record): bool => $record->isLateDelivery())
                ->schema([
                    TextEntry::make('liability_status')->label('Status Verifikasi')->badge(),
                    TextEntry::make('required_date')->label('Tanggal Dibutuhkan PO')->date('d M Y')->placeholder('-'),
                    TextEntry::make('delay_days')->label('Keterlambatan Maksimum')->suffix(' hari'),
                    TextEntry::make('first_late_receipt_date')->label('Penerimaan Terlambat Pertama')->date('d M Y')->placeholder('-'),
                    TextEntry::make('latest_late_receipt_date')->label('Penerimaan Terlambat Terakhir')->date('d M Y')->placeholder('-'),
                    TextEntry::make('scoring_rule_version')->label('Aturan Poin')->placeholder('Belum dinilai'),
                    TextEntry::make('liabilityReviewedBy.name')->label('Diverifikasi Oleh')->placeholder('Belum diverifikasi'),
                    TextEntry::make('liability_reviewed_at')->label('Diverifikasi Pada')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('waiver_reason')->label('Alasan Pengecualian')->placeholder('-')->columnSpanFull()
                        ->visible(fn (VendorComplianceIncident $record): bool => filled($record->waiver_reason)),
                    View::make('filament.helpdesk.vendor-compliance-incidents.po-receipts')->columnSpanFull(),
                ]),
            Section::make('Riwayat Verifikasi')
                ->visible(fn (VendorComplianceIncident $record): bool => $record->isLateDelivery())
                ->schema([
                    RepeatableEntry::make('events')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('event')->label('Kejadian')->formatStateUsing(fn (string $state): string => static::eventLabel($state)),
                        TextEntry::make('actor.name')->label('Oleh')->placeholder('Sistem'),
                        TextEntry::make('created_at')->label('Waktu')->dateTime('d M Y H:i'),
                        TextEntry::make('reason')->label('Catatan')->placeholder('-')
                            ->state(fn ($record): ?string => $record->reason ?? (isset($record->metadata['delay_days']) ? 'Terlambat '.$record->metadata['delay_days'].' hari' : null)),
                    ]),
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
        return $table->recordTitleAttribute('incident_number')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['goodsReceipt:id,reference_number', 'item:id,product_name,uom_name', 'handledBy:id,name']))
            ->columns([
                TextColumn::make('incident_number')->label('Nomor Insiden')->searchable()->copyable(),
                TextColumn::make('occurred_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('supplier_name')->label('Vendor')->searchable()->sortable(),
                TextColumn::make('goodsReceipt.reference_number')->label('Nomor PO')->searchable(),
                TextColumn::make('item.product_name')->label('Produk')->searchable()
                    ->state(fn (VendorComplianceIncident $record): ?string => $record->isLateDelivery() ? 'Level PO' : $record->item?->product_name),
                TextColumn::make('category')->label('Kategori')->badge()->formatStateUsing(fn (string $state): string => static::categoryLabel($state)),
                TextColumn::make('affected_quantity')->label('Qty / Terlambat')
                    ->state(fn (VendorComplianceIncident $record): string => $record->isLateDelivery()
                        ? $record->delay_days.' hari'
                        : rtrim(rtrim(number_format((float) $record->affected_quantity, 4, ',', '.'), '0'), ','))
                    ->description(fn (VendorComplianceIncident $record): ?string => $record->isLateDelivery()
                        ? 'Dibutuhkan '.($record->required_date?->format('d M Y') ?? '-').' · Diterima '.($record->latest_late_receipt_date?->format('d M Y') ?? '-')
                        : null),
                TextColumn::make('liability_status')->label('Verifikasi')->badge()
                    ->state(fn (VendorComplianceIncident $record): ?VendorComplianceLiabilityStatus => $record->isLateDelivery() ? $record->liability_status : null)
                    ->placeholder('-'),
                TextColumn::make('demerit_points')->label('Demerit')->suffix(' Poin')->sortable()->summarize(Sum::make()->label('Total')),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state): string => static::statusOptions()[$state] ?? $state)->color(fn (string $state): string => static::statusColor($state)),
                TextColumn::make('handledBy.name')->label('PIC')->placeholder('-'),
            ])->filters([
                SelectFilter::make('status')->options(static::statusOptions()),
                SelectFilter::make('category')->label('Kategori')->options(static::categoryOptions()),
                SelectFilter::make('liability_status')->label('Verifikasi Keterlambatan')->options([
                    VendorComplianceLiabilityStatus::Pending->value => VendorComplianceLiabilityStatus::Pending->getLabel(),
                    VendorComplianceLiabilityStatus::Confirmed->value => VendorComplianceLiabilityStatus::Confirmed->getLabel(),
                    VendorComplianceLiabilityStatus::Waived->value => VendorComplianceLiabilityStatus::Waived->getLabel(),
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

    /** @return array<string, string> */
    public static function categoryOptions(): array
    {
        return ['document' => 'Dokumen', 'quantity' => 'Quantity', 'quality' => 'Quality', 'packaging' => 'Packaging',
            'cold_chain' => 'Cold Chain', 'shelf_life' => 'Shelf Life', 'sampling' => 'Sampling', 'other' => 'Lainnya',
            VendorComplianceIncident::CATEGORY_LATE_DELIVERY => 'Late Delivery'];
    }

    private static function categoryLabel(string $category): string
    {
        return static::categoryOptions()[$category] ?? $category;
    }

    private static function eventLabel(string $event): string
    {
        return ['candidate_created' => 'Kandidat dibuat', 'delay_updated' => 'Penerimaan parsial tercatat',
            'confirmed' => 'Pelanggaran dikonfirmasi', 'waived' => 'Dikecualikan'][$event] ?? $event;
    }
}
