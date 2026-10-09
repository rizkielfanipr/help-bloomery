<?php

namespace App\Filament\Helpdesk\Concerns;

use App\Services\EsbMasterProductService;
use App\Services\EsbService;
use Filament\Notifications\Notification;
use Throwable;

/**
 * The ESB product picker of the BOM "Tambah Komponen" modal, shared with the Memo Internal
 * "Tambah Product" modal so both look and behave the same (markup in the
 * filament.helpdesk.partials.esb-product-picker-modal partial). Hosts own when the modal opens
 * (`$inlineProductModalOpen`) and what selecting a row does; options here are display data only
 * and must be re-verified server-side before anything is saved.
 */
trait ManagesEsbProductPicker
{
    public array $inlineProductOptions = [];

    public array $inlineProductCategoryOptions = [];

    public array $inlineProductSubCategoryOptions = [];

    public array $inlineProductUnitOptions = [];

    public string $inlineProductNameSearch = '';

    public string $inlineProductCodeSearch = '';

    public string $inlineProductCategoryId = '';

    public string $inlineProductSubCategoryId = '';

    public int $inlineProductPage = 1;

    public int $inlineProductTotal = 0;

    public int $inlineProductPerPage = 10;

    public bool $inlineProductHasNext = false;

    /** Category ID of the ESB product taxonomy with this name (case-insensitive), loading it if needed. */
    protected function inlineProductCategoryIdNamed(string $name): ?int
    {
        try {
            if ($this->inlineProductCategoryOptions === []) {
                $taxonomy = app(EsbMasterProductService::class)->getProductTaxonomy();
                $this->inlineProductCategoryOptions = $taxonomy['categories'];
                $this->inlineProductSubCategoryOptions = $taxonomy['subCategories'];
                $this->inlineProductUnitOptions = app(EsbService::class)->getAllActiveProductUnits();
            }
        } catch (Throwable) {
            return null;
        }

        $id = collect($this->inlineProductCategoryOptions)->search(fn ($label): bool => mb_strtolower(trim((string) $label)) === mb_strtolower($name));

        return $id === false ? null : (int) $id;
    }

    /** Clears search, filters, and results before the picker is opened. */
    protected function resetInlineProductPicker(): void
    {
        $this->inlineProductNameSearch = '';
        $this->inlineProductCodeSearch = '';
        $this->inlineProductCategoryId = '';
        $this->inlineProductSubCategoryId = '';
        $this->inlineProductPage = 1;
        $this->inlineProductOptions = [];
        $this->inlineProductTotal = 0;
        $this->inlineProductHasNext = false;
    }

    public function loadInlineProducts(bool $reset = false): void
    {
        if ($reset) {
            $this->inlineProductPage = 1;
        }

        try {
            if ($this->inlineProductCategoryOptions === []) {
                $taxonomy = app(EsbMasterProductService::class)->getProductTaxonomy();
                $this->inlineProductCategoryOptions = $taxonomy['categories'];
                $this->inlineProductSubCategoryOptions = $taxonomy['subCategories'];
                $this->inlineProductUnitOptions = app(EsbService::class)->getAllActiveProductUnits();
            }

            if (filled($this->inlineProductNameSearch) || filled($this->inlineProductCodeSearch) || filled($this->inlineProductCategoryId) || filled($this->inlineProductSubCategoryId)) {
                $list = app(EsbMasterProductService::class)->getProducts([
                    'page' => $this->inlineProductPage,
                    'limit' => 20,
                    'productName' => trim($this->inlineProductNameSearch),
                    'productCode' => trim($this->inlineProductCodeSearch),
                    'categoryID' => $this->inlineProductCategoryId,
                    'subCategoryID' => $this->inlineProductSubCategoryId,
                ]);
                $this->inlineProductOptions = app(EsbService::class)->getActiveProductDetailsByCodes(
                    array_column($list['data'], 'productCode'),
                );
                $this->inlineProductPage = $list['page'];
                $this->inlineProductTotal = $list['count'];
                $this->inlineProductPerPage = $list['limit'];
                $this->inlineProductHasNext = filled($list['next'])
                    || ($this->inlineProductPage * $this->inlineProductPerPage < $this->inlineProductTotal);
            } else {
                $result = app(EsbService::class)->getActiveProductDetailsPage('', $this->inlineProductPage);
                $this->inlineProductOptions = $result['data'];
                $this->inlineProductPage = $result['page'];
                $this->inlineProductTotal = $result['total'];
                $this->inlineProductPerPage = $result['perPage'];
                $this->inlineProductHasNext = $result['hasNext'];
            }
            $this->inlineProductOptions = app(EsbMasterProductService::class)->filterActiveProductDetails($this->inlineProductOptions);
        } catch (Throwable $exception) {
            $this->inlineProductOptions = [];
            Notification::make()
                ->title('Master Product belum dapat dimuat')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updatedInlineProductNameSearch(): void
    {
        $this->loadInlineProducts(true);
    }

    public function updatedInlineProductCodeSearch(): void
    {
        $this->loadInlineProducts(true);
    }

    public function updatedInlineProductCategoryId(): void
    {
        $this->loadInlineProducts(true);
    }

    public function updatedInlineProductSubCategoryId(): void
    {
        $this->loadInlineProducts(true);
    }

    public function previousInlineProductPage(): void
    {
        if ($this->inlineProductPage > 1) {
            $this->inlineProductPage--;
            $this->loadInlineProducts();
        }
    }

    public function nextInlineProductPage(): void
    {
        if ($this->inlineProductHasNext) {
            $this->inlineProductPage++;
            $this->loadInlineProducts();
        }
    }

    public function goToInlineProductPage(int $page): void
    {
        $lastPage = max(1, (int) ceil($this->inlineProductTotal / max(1, $this->inlineProductPerPage)));
        $this->inlineProductPage = min($lastPage, max(1, $page));
        $this->loadInlineProducts();
    }
}
