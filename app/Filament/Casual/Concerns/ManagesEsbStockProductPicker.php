<?php

namespace App\Filament\Casual\Concerns;

use App\Services\EsbItemJournalService;
use App\Services\EsbService;
use App\Services\EsbStockMovementService;
use Throwable;

/**
 * The "Pilih Produk" sheet of the Employee App (markup in
 * filament.casual.pages.partials.esb-product-picker-sheet), shared so the Item Journal and Receiving
 * Tanpa PO tiles look and behave the same on a phone. Hosts own `$branchId`, `$locationId`,
 * `$locations`, and `$loadError`, and decide when it opens and what selecting a row does.
 */
trait ManagesEsbStockProductPicker
{
    public string $productSearch = '';

    public string $productCodeSearch = '';

    public bool $productPickerOpen = false;

    public int $productPage = 1;

    public int $productTotal = 0;

    public int $productPerPage = 10;

    public bool $productHasNext = false;

    public array $productOptions = [];

    public ?string $stockLoadWarning = null;

    abstract private function pickerCompanyCode(): string;

    /** @return array<int, array<string, mixed>> */
    abstract private function pickerBranches(): array;

    public function loadProducts(bool $resetPage = false): void
    {
        if ($resetPage) {
            $this->productPage = 1;
        }

        try {
            abort_if($this->pickerCompanyCode() === '', 422, 'Pilih ESB Comcode terlebih dahulu.');
            $list = app(EsbItemJournalService::class)->products($this->pickerCompanyCode(), [
                'page' => $this->productPage,
                'limit' => 20,
                'productName' => trim($this->productSearch),
                'productCode' => trim($this->productCodeSearch),
            ]);
            $productOptions = app(EsbService::class)->getActiveProductDetailsByCodes(
                array_column($list['data'], 'productCode'),
            );
            $this->productOptions = $this->withAvailableStocks($productOptions);
            $this->productPage = $list['page'];
            $this->productTotal = $list['count'];
            $this->productPerPage = $list['limit'];
            $this->productHasNext = filled($list['next'])
                || ($this->productPage * $this->productPerPage < $this->productTotal);
            $this->loadError = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->loadError = $exception->getMessage();
            $this->productOptions = [];
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

    /** @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function withAvailableStocks(array $products): array
    {
        if ($this->branchId === '' || $this->locationId === '') {
            return collect($products)->map(fn (array $product): array => array_merge($product, [
                'availableStock' => null,
                'availableStockUnit' => null,
            ]))->all();
        }

        $branch = collect($this->pickerBranches())->first(
            fn (array $candidate): bool => (string) ($candidate['branchID'] ?? '') === (string) $this->branchId,
        );
        $location = collect($this->locations)->first(
            fn (array $candidate): bool => (string) ($candidate['locationID'] ?? '') === (string) $this->locationId,
        );
        if (! is_array($branch) || ! is_array($location)) {
            return $products;
        }

        try {
            $result = app(EsbStockMovementService::class)->availableBalancesForItemJournal(
                $this->pickerCompanyCode(),
                (string) ($branch['branchCode'] ?? ''),
                (int) $location['locationID'],
                (string) ($location['locationName'] ?? ''),
            );
            $balances = $result['balances'];
            $this->stockLoadWarning = null;

            return collect($products)->map(function (array $product) use ($balances): array {
                $key = strtoupper(trim((string) ($product['productCode'] ?? '')))
                    .'|'.strtoupper(trim((string) ($product['unit'] ?? '')));
                $balance = $balances[$key] ?? null;

                return array_merge($product, [
                    'availableStock' => $balance['quantity'] ?? null,
                    'availableStockUnit' => $balance['unit'] ?? null,
                ]);
            })->all();
        } catch (Throwable $exception) {
            report($exception);
            $this->stockLoadWarning = 'Stok saat ini tidak dapat dimuat dari ESB. Pemilihan produk tetap dapat dilanjutkan.';

            return collect($products)->map(fn (array $product): array => array_merge($product, [
                'availableStock' => null,
                'availableStockUnit' => null,
            ]))->all();
        }
    }
}
