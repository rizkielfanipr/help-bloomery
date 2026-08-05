<?php

namespace App\Filament\Helpdesk\Pages;

use App\Filament\Helpdesk\Resources\Projects\ProjectResource;
use App\Models\RndProject;
use App\Models\RndProjectBom;
use App\Models\RndProjectProduct;
use App\Services\EsbCoreService;
use App\Services\EsbService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use UnitEnum;

class CreateBomRecipePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-plus';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $navigationLabel = 'Buat Resep';

    protected static ?string $title = 'Buat Resep Assembly';

    protected static ?string $slug = 'rnd-projects/{project}/products/{product}/bom/create';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.create-bom-recipe';

    public array $data = [];

    public int $projectId;

    public string $projectName = '';

    public int $productId;

    public string $productName = '';

    public bool $isEditing = false;

    #[Url]
    public string $usageType = 'main';

    public string $importBomId = '';

    public array $importBomOptions = [];

    public string $importBomCodeSearch = '';

    public string $importBomNameSearch = '';

    public string $importBomProductSearch = '';

    public string $importBomUnitSearch = '';

    public string $importBomTypeSearch = '';

    public int $importBomPage = 1;

    public int $importBomPerPage = 10;

    public array $products = [];

    public array $selectedProducts = [];

    public array $categoryOptions = [];

    public array $subCategoryOptions = [];

    public array $unitOptions = [];

    public string $productSearch = '';

    public string $productCodeSearch = '';

    public string $productCategoryId = '';

    public string $productSubCategoryId = '';

    public int $productPage = 1;

    public int $productTotal = 0;

    public int $productPerPage = 10;

    public bool $productHasNext = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create bill of materials') ?? false;
    }

    public function getTitle(): string
    {
        return $this->usageType === 'menu' ? 'Buat Resep Menu' : 'Buat Resep Assembly';
    }

    public function mount(?int $project = null, ?int $product = null, ?int $bom = null): void
    {
        abort_unless($project && $product, 404);
        $projectRecord = RndProject::query()->findOrFail($project);
        abort_unless(ProjectResource::canView($projectRecord), 403);
        $productRecord = $projectRecord->products()->findOrFail($product);
        $this->projectId = $projectRecord->id;
        $this->projectName = $projectRecord->name;
        $this->productId = $productRecord->id;
        $this->productName = $productRecord->name;
        $this->usageType = $this->usageType === 'menu' ? 'menu' : 'main';

        $this->data = [
            'bomName' => '',
            'bomCode' => '',
            'productDetailID' => '',
            'notes' => '',
            'bomCostTotal' => 0,
            'accessType' => 0,
            'selectedUserAccess' => [],
            'bomDetails' => [$this->emptyMaterial()],
        ];

    }

    public function loadProducts(bool $reset = false): void
    {
        if ($reset) {
            $this->productPage = 1;
        }

        try {
            if ($this->categoryOptions === []) {
                $taxonomy = app(EsbCoreService::class)->getProductTaxonomy();
                $this->categoryOptions = $taxonomy['categories'];
                $this->subCategoryOptions = $taxonomy['subCategories'];
                $this->unitOptions = app(EsbService::class)->getAllActiveProductUnits();
            }

            if (filled($this->productSearch) || filled($this->productCodeSearch) || filled($this->productCategoryId) || filled($this->productSubCategoryId)) {
                $list = app(EsbCoreService::class)->getProducts([
                    'page' => $this->productPage,
                    'limit' => 20,
                    'productName' => trim($this->productSearch),
                    'productCode' => trim($this->productCodeSearch),
                    'categoryID' => $this->productCategoryId,
                    'subCategoryID' => $this->productSubCategoryId,
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
        } catch (\RuntimeException $exception) {
            $this->products = [];
            Notification::make()
                ->title('Master produk belum dapat dimuat')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function updatedProductSearch(): void
    {
        $this->loadProducts(true);
    }

    public function updatedProductCodeSearch(): void
    {
        $this->loadProducts(true);
    }

    public function updatedProductCategoryId(): void
    {
        $this->loadProducts(true);
    }

    public function updatedProductSubCategoryId(): void
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

    public function selectProduct(string $target, int $index, int $productDetailId): void
    {
        if (! isset($this->products[$productDetailId])) {
            return;
        }

        $this->selectedProducts[$productDetailId] = $this->products[$productDetailId];

        if ($target === 'result') {
            $this->data['productDetailID'] = $productDetailId;
            $this->resetValidation('data.productDetailID');

            return;
        }

        if ($target !== 'material' || ! isset($this->data['bomDetails'][$index])) {
            return;
        }

        $product = $this->products[$productDetailId];
        $this->data['bomDetails'][$index]['productDetailID'] = $productDetailId;
        $this->data['bomDetails'][$index]['lastHPP'] = $product['basePrice'];
        $this->data['bomDetails'][$index]['tolerancePercent'] = $product['receiptTolerance'];
        $this->resetValidation("data.bomDetails.$index.productDetailID");
    }

    public function addMaterial(): void
    {
        $this->data['bomDetails'][] = $this->emptyMaterial();
    }

    public function loadImportBomOptions(): void
    {
        if ($this->importBomOptions !== []) {
            return;
        }

        try {
            $this->importBomOptions = app(EsbCoreService::class)->getAllBillOfMaterials();
            $this->importBomPage = 1;
        } catch (\RuntimeException $exception) {
            Notification::make()->title('Daftar BOM belum dapat dimuat')->body($exception->getMessage())->warning()->send();
        }
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'importBom') && str_ends_with($property, 'Search')) {
            $this->importBomPage = 1;
        }
    }

    public function filteredImportBoms(): array
    {
        return array_values(array_filter($this->importBomOptions, function (array $bom): bool {
            return $this->contains($bom['bomCode'] ?? '', $this->importBomCodeSearch)
                && $this->contains($bom['bomName'] ?? '', $this->importBomNameSearch)
                && $this->contains($bom['productName'] ?? '', $this->importBomProductSearch)
                && $this->contains($bom['uomName'] ?? '', $this->importBomUnitSearch)
                && $this->contains($bom['bomTypeName'] ?? '', $this->importBomTypeSearch);
        }));
    }

    public function importBomRows(): array
    {
        return array_slice(
            $this->filteredImportBoms(),
            ($this->importBomPage - 1) * $this->importBomPerPage,
            $this->importBomPerPage,
        );
    }

    public function goToImportBomPage(int $page): void
    {
        $lastPage = max(1, (int) ceil(count($this->filteredImportBoms()) / $this->importBomPerPage));
        $this->importBomPage = min($lastPage, max(1, $page));
    }

    public function importBomMaterials(): void
    {
        $this->validate(['importBomId' => ['required', 'integer', 'min:1']]);

        try {
            $detail = app(EsbCoreService::class)->getBillOfMaterial((int) $this->importBomId);
            $this->data['bomDetails'] = array_map(
                fn (array $item): array => $this->materialFromBomDetail($item, false),
                $detail['bomDetails'] ?? [],
            );

            foreach ($detail['bomDetails'] ?? [] as $item) {
                $this->rememberBomProduct($item);
            }

            if ($this->data['bomDetails'] === []) {
                $this->data['bomDetails'] = [$this->emptyMaterial()];
            }

            Notification::make()
                ->title('Bahan berhasil disalin')
                ->body(count($detail['bomDetails'] ?? []).' bahan diimpor dari '.$detail['bomName'].'.')
                ->success()
                ->send();
        } catch (\RuntimeException $exception) {
            Notification::make()->title('Bahan gagal diimpor')->body($exception->getMessage())->danger()->send();
        }
    }

    public function removeMaterial(int $index): void
    {
        if (count($this->data['bomDetails']) <= 1) {
            return;
        }

        unset($this->data['bomDetails'][$index]);
        $this->data['bomDetails'] = array_values($this->data['bomDetails']);
    }

    public function create(): void
    {
        $isMenu = $this->usageType === 'menu';

        $validated = $this->validate([
            'data.bomName' => ['required', 'string', 'max:255'],
            'data.bomCode' => ['required', 'string', 'max:100'],
            'data.productDetailID' => $isMenu ? ['nullable', 'integer'] : ['required', 'integer', Rule::in(array_keys($this->selectedProducts))],
            'data.notes' => ['nullable', 'string', 'max:2000'],
            'data.bomCostTotal' => ['required', 'numeric', 'min:0'],
            'data.accessType' => ['required', 'integer', 'in:0,1'],
            'data.selectedUserAccess' => ['array'],
            'data.bomDetails' => ['required', 'array', 'min:1'],
            'data.bomDetails.*.productDetailID' => $isMenu
                ? ['required', 'integer', Rule::in(array_keys($this->selectedProducts))]
                : ['required', 'integer', Rule::in(array_keys($this->selectedProducts)), 'different:data.productDetailID'],
            'data.bomDetails.*.lastHPP' => ['required', 'numeric', 'min:0'],
            'data.bomDetails.*.qty' => ['required', 'numeric', 'gt:0'],
            'data.bomDetails.*.yieldPercent' => ['required', 'numeric', 'between:0,100'],
            'data.bomDetails.*.printGroup' => ['nullable', 'string', 'max:100'],
            'data.bomDetails.*.tolerancePercent' => $isMenu ? ['nullable', 'numeric', 'between:0,100'] : ['required', 'numeric', 'between:0,100'],
        ], [
            'data.bomDetails.*.productDetailID.different' => 'Material tidak boleh sama dengan produk hasil.',
        ]);

        $payload = $validated['data'];
        $payload['bomTypeID'] = $isMenu ? 3 : 1;

        // BOM Menu has no "Product Hasil" concept in ESB's API — only Assembly
        // BOMs carry a top-level productDetailID.
        if ($isMenu) {
            unset($payload['productDetailID']);
        } else {
            $payload['productDetailID'] = (int) $payload['productDetailID'];
        }

        $payload['bomCostTotal'] = (float) $payload['bomCostTotal'];
        $payload['accessType'] = (int) $payload['accessType'];
        $payload['selectedUserAccess'] = $payload['accessType'] === 0 ? [] : array_values($payload['selectedUserAccess']);
        $payload['bomDetails'] = array_map(function (array $item) use ($isMenu): array {
            $row = [
                'ID' => (int) ($item['ID'] ?? 0),
                'productDetailID' => (int) $item['productDetailID'],
                'lastHPP' => (float) $item['lastHPP'],
                'qty' => (float) $item['qty'],
                'yieldPercent' => (float) $item['yieldPercent'],
                'printGroup' => (string) ($item['printGroup'] ?? ''),
                'subtitution' => [],
            ];

            if (! $isMenu) {
                $row['tolerancePercent'] = (float) ($item['tolerancePercent'] ?? 0);
            }

            return $row;
        }, $payload['bomDetails']);
        $payload['bomCosts'] = [];

        try {
            $bomId = $this->persistBom($payload);
            $this->syncProjectBom($bomId, $payload);

            Notification::make()
                ->title($this->isEditing ? 'BOM berhasil diperbarui' : 'Resep berhasil dibuat')
                ->body('BOM ID: '.$bomId)
                ->success()
                ->send();

            $this->redirect($this->successRedirectUrl($bomId));
        } catch (\RuntimeException $exception) {
            Notification::make()->title('Resep gagal dibuat')->body($exception->getMessage())->danger()->send();
        }
    }

    protected function persistBom(array $payload): int
    {
        return app(EsbCoreService::class)->createAssembly($payload);
    }

    protected function successRedirectUrl(int $bomId): string
    {
        return ViewProjectProductPage::getUrl([
            'project' => $this->projectId,
            'product' => $this->productId,
        ]);
    }

    protected function syncProjectBom(int $bomId, array $payload): void
    {
        $resultProduct = $this->selectedProducts[(int) ($payload['productDetailID'] ?? 0)] ?? [];

        $projectBom = RndProjectBom::query()->updateOrCreate(
            ['esb_bom_id' => $bomId],
            [
                'rnd_project_id' => $this->projectId,
                'bom_code' => $payload['bomCode'],
                'bom_name' => $payload['bomName'],
                'product_name' => $resultProduct['productName'] ?? null,
                'uom_name' => ($resultProduct['baseUnit'] ?? '') ?: ($resultProduct['unit'] ?? null),
                'bom_type_name' => $this->usageType === 'menu' ? 'Menu' : 'Assembly',
                'is_active' => true,
                'sync_status' => 'synced',
                'created_by' => auth()->id(),
                'last_synced_at' => now(),
            ],
        );

        RndProjectProduct::query()
            ->where('rnd_project_id', $this->projectId)
            ->findOrFail($this->productId)
            ->boms()
            ->syncWithoutDetaching([$projectBom->id => ['usage_type' => $this->usageType]]);
    }

    protected function emptyMaterial(): array
    {
        return [
            'ID' => 0,
            'productDetailID' => '',
            'lastHPP' => 0,
            'qty' => 1,
            'yieldPercent' => 0,
            'printGroup' => '',
            'tolerancePercent' => 0,
        ];
    }

    protected function materialFromBomDetail(array $item, bool $preserveId = true): array
    {
        return [
            'ID' => $preserveId ? (int) ($item['ID'] ?? 0) : 0,
            'productDetailID' => (int) ($item['productDetailID'] ?? 0),
            'lastHPP' => (float) ($item['lastHpp'] ?? $item['lastHPP'] ?? 0),
            'qty' => (float) ($item['qty'] ?? 0),
            'yieldPercent' => (float) ($item['yieldPercent'] ?? 0),
            'printGroup' => (string) ($item['printGroup'] ?? ''),
            'tolerancePercent' => (float) ($item['tolerancePercent'] ?? 0),
        ];
    }

    protected function rememberBomProduct(array $item): void
    {
        $detailId = (int) ($item['productDetailID'] ?? 0);
        if ($detailId < 1) {
            return;
        }

        $unit = (string) ($item['uomName'] ?? '');
        $this->selectedProducts[$detailId] = [
            'productDetailID' => $detailId,
            'productID' => (int) ($item['productID'] ?? 0),
            'productCode' => (string) ($item['productCode'] ?? ''),
            'productName' => (string) ($item['productName'] ?? ''),
            'categoryName' => '',
            'subCategoryName' => '',
            'unit' => $unit,
            'baseUnit' => $unit,
            'conversionFactor' => (float) ($item['convertionQty'] ?? 1),
            'sku' => '',
            'basePrice' => (float) ($item['lastHpp'] ?? 0),
            'receiptTolerance' => (float) ($item['tolerancePercent'] ?? 0),
        ];
    }

    private function contains(mixed $value, string $search): bool
    {
        return $search === '' || str_contains(mb_strtolower((string) $value), mb_strtolower($search));
    }
}
