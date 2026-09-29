<?php

namespace App\Filament\Helpdesk\Pages;

use App\Actions\Rnd\Bom\UpdateEsbBillOfMaterialAction;
use App\Enums\RndBomCatalogSyncStatus;
use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use App\Exceptions\Rnd\BomConflictException;
use App\Exceptions\Rnd\BomInvariantException;
use App\Models\RndBomCatalog;
use App\Models\RndBomChangeLog;
use App\Services\EsbBillOfMaterialService;
use App\Services\EsbMasterProductService;
use App\Services\EsbService;
use App\Services\Rnd\Bom\BomChangeComparator;
use App\Services\Rnd\Bom\BomPayloadBuilder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

/**
 * Edits one BOM Assembly/Menu directly (no Project required) through the same centralized
 * mutation Action as the Project inline editor (docs/rnd-bom-adjustment-prd.md §11-§13).
 */
class EditBomAdjustmentPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Research & Development';

    protected static ?string $title = 'Edit BOM';

    protected static ?string $slug = 'bom-adjustments/{bomId}/edit';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.helpdesk.pages.edit-bom-adjustment';

    public int $bomId;

    public array $detail = [];

    public array $draft = [];

    public ?string $loadedEditedDate = null;

    public string $reason = '';

    public bool $loading = true;

    public ?string $loadError = null;

    public bool $previewOpen = false;

    public string $productNameSearch = '';

    public string $productCodeSearch = '';

    public string $productCategoryId = '';

    public string $productSubCategoryId = '';

    public int $productPage = 1;

    public int $productTotal = 0;

    public int $productPerPage = 20;

    public bool $productHasNext = false;

    public array $productOptions = [];

    public array $categoryOptions = [];

    public array $subCategoryOptions = [];

    public array $unitOptions = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('edit bill of materials') ?? false;
    }

    public function mount(int $bomId): void
    {
        $this->bomId = $bomId;
        $this->loadDetail();
    }

    public function getTitle(): string
    {
        return $this->detail['bomName'] ?? 'Edit BOM';
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->icon('heroicon-o-arrow-left')
                ->iconButton()
                ->color('gray')
                ->url(BomAdjustmentPage::getUrl()),
        ];
    }

    public function loadDetail(): void
    {
        $this->loading = true;
        $this->loadError = null;

        try {
            $detail = app(EsbBillOfMaterialService::class)->getBillOfMaterial($this->bomId);
            $this->detail = $detail;
            $this->loadedEditedDate = $detail['editedDate'] ?? null;
            $this->draft = [
                'productDetailID' => $detail['productDetailID'] ?? null,
                'productName' => $detail['productName'] ?? '',
                'productCode' => $detail['productCode'] ?? '',
                'uomName' => $detail['uomName'] ?? '',
                'bomDetails' => collect($detail['bomDetails'] ?? [])->map(fn (array $item): array => [
                    'ID' => (int) ($item['ID'] ?? 0),
                    'productDetailID' => (int) ($item['productDetailID'] ?? 0),
                    'productCode' => (string) ($item['productCode'] ?? ''),
                    'productName' => (string) ($item['productName'] ?? ''),
                    'uomName' => (string) ($item['uomName'] ?? ''),
                    'lastHPP' => (float) ($item['lastHpp'] ?? $item['lastHPP'] ?? 0),
                    'qty' => (float) ($item['qty'] ?? 0),
                    'yieldPercent' => (float) ($item['yieldPercent'] ?? 0),
                    'tolerancePercent' => (float) ($item['tolerancePercent'] ?? 0),
                    'printGroup' => (string) ($item['printGroup'] ?? ''),
                ])->values()->all(),
            ];
        } catch (Throwable $exception) {
            $this->loadError = $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function isMenu(): bool
    {
        return app(BomPayloadBuilder::class)->isMenu($this->detail);
    }

    public function removeComponent(int $index): void
    {
        if (count($this->draft['bomDetails'] ?? []) <= 1) {
            $this->addError('draft.bomDetails', 'BOM wajib memiliki minimal satu komponen.');

            return;
        }

        unset($this->draft['bomDetails'][$index]);
        $this->draft['bomDetails'] = array_values($this->draft['bomDetails']);
    }

    public function updatedProductNameSearch(): void
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

    public function loadProducts(bool $reset = false): void
    {
        if ($reset) {
            $this->productPage = 1;
        }

        try {
            if ($this->categoryOptions === []) {
                $taxonomy = app(EsbMasterProductService::class)->getProductTaxonomy();
                $this->categoryOptions = $taxonomy['categories'];
                $this->subCategoryOptions = $taxonomy['subCategories'];
                $this->unitOptions = app(EsbService::class)->getAllActiveProductUnits();
            }

            if (filled($this->productNameSearch) || filled($this->productCodeSearch) || filled($this->productCategoryId) || filled($this->productSubCategoryId)) {
                $list = app(EsbMasterProductService::class)->getProducts([
                    'page' => $this->productPage,
                    'limit' => 20,
                    'productName' => trim($this->productNameSearch),
                    'productCode' => trim($this->productCodeSearch),
                    'categoryID' => $this->productCategoryId,
                    'subCategoryID' => $this->productSubCategoryId,
                ]);
                $this->productOptions = app(EsbService::class)->getActiveProductDetailsByCodes(
                    array_column($list['data'], 'productCode'),
                );
                $this->productPage = $list['page'];
                $this->productTotal = $list['count'];
                $this->productPerPage = $list['limit'];
                $this->productHasNext = filled($list['next'])
                    || ($this->productPage * $this->productPerPage < $this->productTotal);
            } else {
                $result = app(EsbService::class)->getActiveProductDetailsPage('', $this->productPage);
                $this->productOptions = $result['data'];
                $this->productPage = $result['page'];
                $this->productTotal = $result['total'];
                $this->productPerPage = $result['perPage'];
                $this->productHasNext = $result['hasNext'];
            }
            $this->productOptions = app(EsbMasterProductService::class)->filterActiveProductDetails($this->productOptions);
        } catch (Throwable $exception) {
            $this->productOptions = [];
            Notification::make()->title('Master Product belum dapat dimuat')->body($exception->getMessage())->danger()->send();
        }
    }

    public function selectProduct(string $target, int $productDetailId): void
    {
        $product = $this->productOptions[$productDetailId] ?? null;
        abort_unless(is_array($product), 422);

        if ($target === 'result') {
            $this->draft['productDetailID'] = $productDetailId;
            $this->draft['productName'] = $product['productName'];
            $this->draft['productCode'] = $product['productCode'];
            $this->draft['uomName'] = $product['baseUnit'] ?: $product['unit'];

            return;
        }

        $usedIds = collect($this->draft['bomDetails'] ?? [])->pluck('productDetailID')->map(fn ($id): int => (int) $id);
        if ($usedIds->contains($productDetailId)) {
            Notification::make()->title('Product sudah menjadi komponen BOM')->warning()->send();

            return;
        }

        $this->draft['bomDetails'][] = [
            'ID' => 0,
            'productDetailID' => $productDetailId,
            'productCode' => $product['productCode'],
            'productName' => $product['productName'],
            'uomName' => $product['baseUnit'] ?: $product['unit'],
            'lastHPP' => (float) $product['basePrice'],
            'qty' => 1,
            'yieldPercent' => 0,
            'tolerancePercent' => (float) $product['receiptTolerance'],
            'printGroup' => '',
        ];
    }

    public function previewChanges(): array
    {
        $after = $this->draft + ['bomDetails' => $this->draft['bomDetails'] ?? []];

        return app(BomChangeComparator::class)->compare($this->detail, $after);
    }

    /** @return Collection<int, RndBomChangeLog> */
    public function changeTimeline(): Collection
    {
        return RndBomChangeLog::query()
            ->where('esb_bom_id', $this->bomId)
            ->with('changedBy')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    /** @return array{diff: array<string, mixed>|null, is_confirmed: bool, rows: list<array{product: string, unit: string, before: ?float, after: ?float, note: string}>} */
    public function timelineDetailDiff(RndBomChangeLog $log): array
    {
        return app(BomChangeComparator::class)->diffRowsForLog($log);
    }

    public function openPreview(): void
    {
        $this->validateDraft();
        $this->previewOpen = true;
    }

    public function closePreview(): void
    {
        $this->previewOpen = false;
    }

    private function validateDraft(): array
    {
        return $this->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'draft.productDetailID' => $this->isMenu() ? ['nullable', 'integer'] : ['required', 'integer', 'min:1'],
            'draft.productName' => ['nullable', 'string', 'max:255'],
            'draft.productCode' => ['nullable', 'string', 'max:100'],
            'draft.uomName' => ['nullable', 'string', 'max:100'],
            'draft.bomDetails' => ['required', 'array', 'min:1'],
            'draft.bomDetails.*.ID' => ['required', 'integer', 'min:0'],
            'draft.bomDetails.*.productDetailID' => ['required', 'integer', 'min:1'],
            'draft.bomDetails.*.productCode' => ['nullable', 'string', 'max:100'],
            'draft.bomDetails.*.productName' => ['nullable', 'string', 'max:255'],
            'draft.bomDetails.*.uomName' => ['nullable', 'string', 'max:100'],
            'draft.bomDetails.*.qty' => ['required', 'numeric', 'gt:0'],
            'draft.bomDetails.*.lastHPP' => ['required', 'numeric', 'min:0'],
            'draft.bomDetails.*.yieldPercent' => ['required', 'numeric', 'between:0,100'],
            'draft.bomDetails.*.tolerancePercent' => $this->isMenu() ? ['nullable', 'numeric', 'between:0,100'] : ['required', 'numeric', 'between:0,100'],
            'draft.bomDetails.*.printGroup' => ['nullable', 'string', 'max:100'],
        ]);
    }

    public function submit(): void
    {
        $validated = $this->validateDraft();

        try {
            $changeLog = app(UpdateEsbBillOfMaterialAction::class)->execute(
                bomId: $this->bomId,
                draft: $validated['draft'],
                loadedEditedDate: $this->loadedEditedDate,
                reason: $validated['reason'],
                source: RndBomChangeLogSource::BomAdjustment,
                actor: Auth::user(),
            );

            if ($changeLog->status !== RndBomChangeLogStatus::Success) {
                $this->previewOpen = false;
                Notification::make()
                    ->title('BOM gagal diperbarui')
                    ->body($changeLog->error_message ?? 'Hasil mutation ESB belum dapat dipastikan.')
                    ->danger()
                    ->send();

                return;
            }

            $this->syncLocalCatalog($changeLog->after_snapshot);

            $this->previewOpen = false;
            Notification::make()->title('BOM berhasil diperbarui')->success()->send();
            $this->redirect(BomAdjustmentPage::getUrl());
        } catch (BomConflictException $exception) {
            $this->previewOpen = false;
            $this->addError('draft.bomDetails', $exception->getMessage());
        } catch (BomInvariantException $exception) {
            $this->previewOpen = false;
            $this->addError("draft.{$exception->field}", $exception->getMessage());
        }
    }

    private function syncLocalCatalog(array $afterSnapshot): void
    {
        $catalog = RndBomCatalog::query()->firstOrNew(['esb_bom_id' => $this->bomId]);
        $catalog->fill([
            'bom_code' => $afterSnapshot['bomCode'] ?? null,
            'bom_name' => $afterSnapshot['bomName'] ?? null,
            'bom_type_id' => $afterSnapshot['bomTypeID'] ?? null,
            'bom_type_name' => $afterSnapshot['bomTypeName'] ?? null,
            'product_detail_id' => $afterSnapshot['productDetailID'] ?? null,
            'product_code' => $afterSnapshot['productCode'] ?? null,
            'product_name' => $afterSnapshot['productName'] ?? null,
            'uom_name' => $afterSnapshot['uomName'] ?? null,
            'component_count' => count($afterSnapshot['bomDetails'] ?? []),
            'detail_snapshot' => $afterSnapshot,
            'esb_edited_at' => $afterSnapshot['editedDate'] ?? null,
            'sync_status' => RndBomCatalogSyncStatus::Synced,
            'last_synced_at' => now(),
        ]);

        if (! $catalog->exists) {
            $catalog->is_active = true;
        }

        $catalog->save();
    }
}
