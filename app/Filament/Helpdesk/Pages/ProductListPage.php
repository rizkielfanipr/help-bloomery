<?php

namespace App\Filament\Helpdesk\Pages;

use App\Models\Location;
use App\Models\ProductSetting;
use App\Services\EsbCoreService;
use App\Services\EsbService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use RuntimeException;
use UnitEnum;

class ProductListPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected string $view = 'filament.helpdesk.pages.product-list-page';

    public string $productSearch = '';

    public string $productCodeSearch = '';

    public int $productPage = 1;

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    public int $productTotal = 0;

    public int $productPerPage = 10;

    public bool $productHasNext = false;

    /** @var array<string, array{expiry_days: ?int, locations_count: int}> */
    public array $productSettingsByCode = [];

    public bool $showSettingsModal = false;

    public ?string $editingProductCode = null;

    public ?string $editingProductName = null;

    /** @var array<string, mixed> */
    public array $settingsData = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view product list') ?? false;
    }

    public function getTitle(): string
    {
        return 'Product List';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view product list'), 403);

        $this->settingsData = $this->defaultSettingsData();
        $this->loadProducts();
    }

    /** @return array<string, mixed> */
    protected function defaultSettingsData(): array
    {
        return [
            'expiry_days' => null,
            'barcode_value' => null,
            'location_ids' => [],
        ];
    }

    public function settingsForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('expiry_days')
                    ->label('Standar Masa Kedaluwarsa (hari)')
                    ->numeric()
                    ->integer()
                    ->minValue(0),

                TextInput::make('barcode_value')
                    ->label('Kode Barcode')
                    ->maxLength(100)
                    ->helperText('Kosongkan untuk memakai kode produk sebagai barcode.'),

                Select::make('location_ids')
                    ->label('Lokasi Penyimpanan')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => Location::query()
                        ->with('branch')
                        ->orderBy('branch_id')
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Location $location): array => [
                            $location->id => "{$location->branch->name} — {$location->code} ({$location->name})",
                        ])
                        ->all()),
            ])
            ->statePath('settingsData');
    }

    public function loadProducts(bool $reset = false): void
    {
        if ($reset) {
            $this->productPage = 1;
        }

        try {
            if (filled($this->productSearch) || filled($this->productCodeSearch)) {
                // Browsing "all products" and searching hit two different ESB
                // endpoints with different matching behaviour: the master
                // product endpoint (getActiveProductDetailsPage) only does a
                // strict/prefix match, while the core product-list endpoint
                // (getProducts) does the fuzzy search users expect — the same
                // split CreateBomRecipePage's product picker modal uses.
                $list = app(EsbCoreService::class)->getProducts([
                    'page' => $this->productPage,
                    'limit' => 20,
                    'productName' => trim($this->productSearch),
                    'productCode' => trim($this->productCodeSearch),
                ]);

                $this->products = app(EsbService::class)->getActiveProductDetailsByCodes(
                    array_column($list['data'], 'productCode'),
                );
                $this->productPage = $list['page'];
                $this->productTotal = $list['count'];
                $this->productPerPage = $list['limit'];
                $this->productHasNext = filled($list['next'])
                    || ($this->productPage * $this->productPerPage < $this->productTotal);
            } else {
                $result = app(EsbService::class)->getActiveProductDetailsPage('', $this->productPage);

                $this->products = $result['data'];
                $this->productPage = $result['page'];
                $this->productTotal = $result['total'];
                $this->productPerPage = $result['perPage'];
                $this->productHasNext = $result['hasNext'];
            }

            $this->loadProductSettings();
        } catch (RuntimeException $exception) {
            $this->products = [];
            $this->productSettingsByCode = [];

            Notification::make()
                ->title('Master produk belum dapat dimuat')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function loadProductSettings(): void
    {
        $codes = collect($this->products)->pluck('productCode')->unique()->all();

        $this->productSettingsByCode = ProductSetting::query()
            ->whereIn('product_code', $codes)
            ->withCount('locations')
            ->get()
            ->keyBy('product_code')
            ->map(fn (ProductSetting $setting): array => [
                'expiry_days' => $setting->expiry_days,
                'locations_count' => $setting->locations_count,
            ])
            ->all();
    }

    public function updatedProductSearch(): void
    {
        $this->loadProducts(true);
    }

    public function updatedProductCodeSearch(): void
    {
        $this->loadProducts(true);
    }

    public function previousProductPage(): void
    {
        if ($this->productPage > 1) {
            $this->productPage--;
            $this->loadProducts();
        }
    }

    public function nextProductPage(): void
    {
        if ($this->productHasNext) {
            $this->productPage++;
            $this->loadProducts();
        }
    }

    public function goToProductPage(int $page): void
    {
        $lastPage = max(1, (int) ceil($this->productTotal / max(1, $this->productPerPage)));
        $this->productPage = min($lastPage, max(1, $page));
        $this->loadProducts();
    }

    public function openSettingsModal(string $productCode, string $productName): void
    {
        abort_unless(auth()->user()?->can('edit product list'), 403);

        $setting = ProductSetting::query()->firstOrCreate(['product_code' => $productCode]);

        $this->editingProductCode = $productCode;
        $this->editingProductName = $productName;
        $this->settingsData = [
            'expiry_days' => $setting->expiry_days,
            'barcode_value' => $setting->barcode_value,
            'location_ids' => $setting->locations()->pluck('locations.id')->all(),
        ];
        $this->showSettingsModal = true;
    }

    public function cancelSettingsModal(): void
    {
        $this->showSettingsModal = false;
        $this->editingProductCode = null;
        $this->editingProductName = null;
        $this->settingsData = $this->defaultSettingsData();
    }

    public function saveSettings(): void
    {
        abort_unless(auth()->user()?->can('edit product list'), 403);

        $data = $this->settingsForm->getState();

        $setting = ProductSetting::query()->where('product_code', $this->editingProductCode)->firstOrFail();
        $setting->update([
            'expiry_days' => $data['expiry_days'],
            'barcode_value' => $data['barcode_value'] ?: null,
        ]);
        $setting->locations()->sync($data['location_ids'] ?? []);

        $this->loadProductSettings();
        $this->cancelSettingsModal();

        Notification::make()->title('Pengaturan produk tersimpan')->success()->send();
    }
}
