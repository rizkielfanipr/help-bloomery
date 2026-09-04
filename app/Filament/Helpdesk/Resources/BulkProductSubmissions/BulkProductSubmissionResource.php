<?php

namespace App\Filament\Helpdesk\Resources\BulkProductSubmissions;

use App\Filament\Helpdesk\Concerns\HasPermissions;
use App\Filament\Helpdesk\Resources\BulkProductSubmissions\Pages\CreateBulkProductSubmission;
use App\Filament\Helpdesk\Resources\BulkProductSubmissions\Pages\ListBulkProductSubmissions;
use App\Filament\Helpdesk\Resources\BulkProductSubmissions\Pages\ViewBulkProductSubmission;
use App\Models\BulkProductSubmission;
use App\Services\EsbCompanyProductService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

class BulkProductSubmissionResource extends Resource
{
    use HasPermissions;

    protected static string $permissionPrefix = 'bulk product submissions';

    protected static ?string $model = BulkProductSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static string|UnitEnum|null $navigationGroup = 'Information Technology';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Bulk Data Product';

    protected static ?string $modelLabel = 'Bulk Data Produk';

    protected static ?string $pluralModelLabel = 'Bulk Data Product';

    protected static ?string $slug = 'bulk-data/product';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Operasi dan Target')->schema([
                Select::make('operation')
                    ->label('Operasi')
                    ->options(['create' => 'Create Product', 'update' => 'Update Product'])
                    ->default('create')->required()->live(),
                CheckboxList::make('target_comcodes')
                    ->label('Target Comcode')
                    ->options(array_combine(BulkProductSubmission::COMCODES, BulkProductSubmission::COMCODES))
                    ->default(['BLSS'])
                    ->required()->minItems(1)->columns(5)->bulkToggleable()->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('payload.categoryID', null);
                        $set('payload.subCategoryID', null);
                    }),
            ]),
            Section::make('Identitas Remote untuk Update')
                ->description('Product ID pada masing-masing company dapat berbeda.')
                ->schema(collect(BulkProductSubmission::COMCODES)->map(
                    fn (string $comcode): TextInput => TextInput::make("remote_product_ids.{$comcode}")
                        ->label("Product ID {$comcode}")->numeric()->minValue(1)
                        ->required(fn (Get $get): bool => $get('operation') === 'update' && in_array($comcode, $get('target_comcodes') ?? [], true)),
                )->all())
                ->columns(5)
                ->visible(fn (Get $get): bool => $get('operation') === 'update'),
            Section::make('Data Produk')->schema([
                Select::make('payload.categoryID')
                    ->label('Category')
                    ->options(fn (Get $get): array => self::taxonomyOptions($get, 'categories'))
                    ->searchable()->preload()->native(false)->required()->live()
                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                        $set('payload.subCategoryID', null);
                        if ($get('operation') !== 'create' || blank($state)) {
                            return;
                        }

                        self::generateProductCode($get, $set, (int) $state);
                    })
                    ->disabled(fn (Get $get): bool => self::selectedComcode($get) === null)
                    ->placeholder('Pilih target comcode terlebih dahulu')
                    ->helperText('Daftar diambil dari comcode pertama yang dipilih.'),
                Select::make('payload.subCategoryID')
                    ->label('Subcategory')
                    ->options(fn (Get $get): array => self::subCategoryOptions($get))
                    ->searchable()->preload()->native(false)->required()
                    ->disabled(fn (Get $get): bool => self::selectedComcode($get) === null || blank($get('payload.categoryID')))
                    ->placeholder('Pilih category terlebih dahulu'),
                TextInput::make('payload.productCode')
                    ->label('Product Code')->maxLength(50)->readOnly()
                    ->suffixAction(
                        Action::make('generateProductCode')
                            ->label('Generate')
                            ->icon('heroicon-o-arrow-path')
                            ->disabled(fn (Get $get): bool => $get('operation') !== 'create' || blank($get('payload.categoryID')))
                            ->action(fn (Get $get, Set $set) => self::generateProductCode($get, $set, (int) $get('payload.categoryID'), true)),
                    )
                    ->helperText('Otomatis dari kode terakhir category; gunakan tombol Generate untuk memuat ulang.'),
                TextInput::make('payload.productName')->label('Product Name')->required()->maxLength(100),
                Toggle::make('payload.requestable')->label('Requestable')->default(true),
                Toggle::make('payload.purchasable')->label('Purchasable')->default(true),
                Toggle::make('payload.saleable')->label('Saleable')->default(false),
                Toggle::make('payload.vat')->label('VAT')->default(false),
                Select::make('payload.flagLuxuryItem')->label('Luxury Item')->options([0 => 'Non Luxury', 1 => 'Luxury'])->default(0)->required(),
                TextInput::make('payload.receiptTolerance')->label('Receipt Tolerance (%)')->numeric()->default(0)->minValue(0),
                TextInput::make('payload.bomID')->label('BOM ID')->numeric()->minValue(1),
                TextInput::make('payload.coretaxProductCodeID')->label('Coretax Product Code ID')->numeric()->minValue(1),
                Toggle::make('payload.pushToGoApp')->label('Push to Go App')->default(false),
                Textarea::make('payload.notes')->label('Notes')->maxLength(100)->columnSpanFull(),
            ])->columns(2),
            Section::make('Custom Fields')->collapsed()->schema([
                TextInput::make('payload.customFields.field1')->label('Field 1')->maxLength(50),
                TextInput::make('payload.customFields.field2')->label('Field 2')->maxLength(50),
                TextInput::make('payload.customFields.field3')->label('Field 3')->maxLength(50),
                TextInput::make('payload.customFields.field4')->label('Field 4')->maxLength(50),
                TextInput::make('payload.customFields.field5')->label('Field 5')->maxLength(50),
            ])->columns(5),
            Section::make('Unit Produk')->schema([
                Repeater::make('payload.productDetails')
                    ->label('Product Details')
                    ->schema([
                        Select::make('uomID')
                            ->label('Unit')
                            ->options(config('esb.core.uoms', []))
                            ->searchable()->preload()->native(false)->required()->live()
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                $unit = strtoupper((string) config("esb.core.uoms.{$state}", ''));
                                $set('uomName', $unit);
                                $set('sku', self::sku((string) $get('../../productCode'), $unit));
                            }),
                        Hidden::make('uomName'),
                        TextInput::make('basePrice')->label('Base Price')->numeric()->default(0)->minValue(0)->required(),
                        TextInput::make('sku')->label('SKU')->maxLength(50)->readOnly()
                            ->helperText('Otomatis: Product Code-Unit'),
                        TextInput::make('qty')->label('Qty / Conversion')->numeric()->default(1)->gt(0)->required(),
                        TextInput::make('cubication')->label('Cubication')->numeric()->default(0)->minValue(0),
                        TextInput::make('weight')->label('Weight')->numeric()->default(0)->minValue(0),
                        Toggle::make('isStock')->label('Stock')->default(true)->fixIndistinctState(),
                        Toggle::make('isPurchase')->label('Purchase')->default(true),
                        Toggle::make('isTransfer')->label('Transfer')->default(true),
                        Toggle::make('isSales')->label('Sales')->default(false),
                        Toggle::make('isBase')->label('Base')->default(true)->fixIndistinctState(),
                        Toggle::make('flagActive')->label('Active')->default(true),
                        TextInput::make('menuID')->label('Menu ID')->numeric()->minValue(1),
                        ...collect(BulkProductSubmission::COMCODES)->map(
                            fn (string $comcode): TextInput => TextInput::make("productDetailIDs.{$comcode}")
                                ->label("Detail ID {$comcode}")->numeric()->minValue(1)
                                ->visible(fn (Get $get): bool => $get('../../operation') === 'update')
                                ->required(fn (Get $get): bool => $get('../../operation') === 'update' && in_array($comcode, $get('../../target_comcodes') ?? [], true)),
                        )->all(),
                    ])
                    ->columns(4)->defaultItems(1)->minItems(1)->itemNumbers()->collapsible()
                    ->addActionLabel('Tambah Unit'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Submission')->schema([
                TextEntry::make('operation')->badge()->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextEntry::make('product_code')->label('Product Code')->placeholder('—'),
                TextEntry::make('product_name')->label('Product Name'),
                TextEntry::make('status')->badge()->color(fn (string $state): string => self::statusColor($state)),
                TextEntry::make('creator.name')->label('Dibuat oleh')->placeholder('—'),
                TextEntry::make('submitted_at')->dateTime('d M Y H:i'),
            ])->columns(3),
            Section::make('Hasil per Comcode')->schema([
                RepeatableEntry::make('items')->label('')->schema([
                    TextEntry::make('comcode')->badge(),
                    TextEntry::make('status')->badge()->color(fn (string $state): string => self::statusColor($state)),
                    TextEntry::make('remote_product_id')->label('Product ID')->placeholder('—'),
                    TextEntry::make('attempts')->label('Percobaan'),
                    TextEntry::make('error_message')->label('Error')->placeholder('—')->columnSpanFull(),
                ])->columns(4),
            ]),
            Section::make('Payload')->collapsed()->schema([
                TextEntry::make('payload_json')
                    ->label('Snapshot Payload')
                    ->state(fn (BulkProductSubmission $record): string => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                    ->fontFamily('mono')
                    ->copyable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Batch')->formatStateUsing(fn (int $state): string => '#'.$state)->sortable(),
            TextColumn::make('operation')->label('Operasi')->badge()->formatStateUsing(fn (string $state): string => strtoupper($state)),
            TextColumn::make('product_code')->label('Product Code')->searchable()->placeholder('—'),
            TextColumn::make('product_name')->label('Product Name')->searchable()->limit(40),
            TextColumn::make('target_comcodes')->label('Comcode')->badge()->separator(','),
            TextColumn::make('status')->badge()->color(fn (string $state): string => self::statusColor($state)),
            TextColumn::make('creator.name')->label('Dibuat oleh')->placeholder('—'),
            TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([ViewAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBulkProductSubmissions::route('/'),
            'create' => CreateBulkProductSubmission::route('/create'),
            'view' => ViewBulkProductSubmission::route('/{record}'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'succeeded' => 'success', 'partial' => 'warning', 'failed' => 'danger',
            'processing' => 'info', default => 'gray',
        };
    }

    private static function taxonomyOptions(Get $get, string $key): array
    {
        $taxonomy = self::taxonomy($get);

        return is_array($taxonomy[$key] ?? null) ? $taxonomy[$key] : [];
    }

    private static function subCategoryOptions(Get $get): array
    {
        $categoryId = (int) $get('payload.categoryID');
        $taxonomy = self::taxonomy($get);

        return is_array($taxonomy['subCategoriesByCategory'][$categoryId] ?? null)
            ? $taxonomy['subCategoriesByCategory'][$categoryId]
            : [];
    }

    private static function taxonomy(Get $get): array
    {
        $comcode = self::selectedComcode($get);
        if ($comcode === null) {
            return [];
        }

        try {
            return (new EsbCompanyProductService)->taxonomy($comcode);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private static function selectedComcode(Get $get): ?string
    {
        $selected = $get('target_comcodes') ?? [];

        return collect(BulkProductSubmission::COMCODES)
            ->first(fn (string $comcode): bool => in_array($comcode, $selected, true));
    }

    private static function suggestedProductCode(Get $get, int $categoryId): ?string
    {
        $comcode = self::selectedComcode($get);
        if ($comcode === null) {
            return null;
        }

        try {
            return (new EsbCompanyProductService)->suggestNextProductCode($comcode, $categoryId);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private static function generateProductCode(Get $get, Set $set, int $categoryId, bool $notifyOnFailure = false): void
    {
        $suggestion = self::suggestedProductCode($get, $categoryId);
        if ($suggestion !== null) {
            $set('payload.productCode', $suggestion);
            self::syncSkus($get, $set, $suggestion);

            return;
        }

        $set('payload.productCode', null);
        self::syncSkus($get, $set, '');

        if ($notifyOnFailure) {
            Notification::make()
                ->warning()
                ->title('Product Code belum dapat dibuat')
                ->body('Belum ditemukan kode produk bernomor pada category ini.')
                ->send();
        }
    }

    private static function syncSkus(Get $get, Set $set, string $productCode): void
    {
        $details = $get('payload.productDetails') ?? [];
        foreach ($details as &$detail) {
            $unit = strtoupper((string) ($detail['uomName'] ?? config('esb.core.uoms.'.($detail['uomID'] ?? ''), '')));
            $detail['uomName'] = $unit;
            $detail['sku'] = self::sku($productCode, $unit);
        }

        $set('payload.productDetails', $details);
    }

    private static function sku(string $productCode, string $unit): string
    {
        $productCode = strtoupper(trim($productCode));
        $unit = strtoupper(trim($unit));

        return $productCode !== '' && $unit !== '' ? $productCode.'-'.$unit : '';
    }
}
