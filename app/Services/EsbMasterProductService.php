<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EsbMasterProductService
{
    public function __construct(private readonly EsbGlobalCoreClient $client) {}

    /** @return array{page:int,limit:int,count:int,data:array<int,mixed>,prev:?string,next:?string} */
    public function getProducts(array $filters = []): array
    {
        $result = $this->client->successfulResult(
            $this->client->request('get', '/product/list', array_filter([
                'page' => max(1, (int) ($filters['page'] ?? 1)),
                'limit' => min(100, max(1, (int) ($filters['limit'] ?? 20))),
                'productName' => $filters['productName'] ?? null,
                'productCode' => $filters['productCode'] ?? null,
                'categoryID' => $filters['categoryID'] ?? null,
                'subCategoryID' => $filters['subCategoryID'] ?? null,
                'flagActive' => array_key_exists('flagActive', $filters) ? (int) $filters['flagActive'] : 1,
            ], fn ($value) => $value !== null && $value !== '')),
            'mengambil daftar produk',
        );

        return [
            'page' => (int) ($result['page'] ?? 1),
            'limit' => (int) ($result['limit'] ?? 20),
            'count' => (int) ($result['count'] ?? 0),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'prev' => ($result['prev'] ?? null) ?: null,
            'next' => ($result['next'] ?? null) ?: null,
        ];
    }

    /** @return array<int, array> */
    public function getAllProducts(): array
    {
        $all = [];
        $page = 1;
        do {
            $result = $this->getProducts(['page' => $page, 'limit' => 100]);
            array_push($all, ...$result['data']);
            $hasNext = filled($result['next']) || (($result['page'] * $result['limit']) < $result['count']);
            $page++;
        } while ($hasNext && $page <= 500);

        return $all;
    }

    public function findProductByExactName(string $productName): ?array
    {
        return collect($this->getProducts(['limit' => 100, 'productName' => $productName])['data'])
            ->first(fn (array $product): bool => mb_strtolower(trim((string) ($product['productName'] ?? ''))) === mb_strtolower(trim($productName)));
    }

    public function findProductById(int $productId): ?array
    {
        if ($productId < 1) {
            return null;
        }

        $result = $this->client->successfulResult(
            $this->client->request('get', '/product/'.$productId),
            'mengambil detail Master Product',
        );

        return $result === [] ? null : $result;
    }

    /**
     * Keep one authoritative active row for every product and UOM combination.
     *
     * Legacy ESB products may contain duplicate Product Detail rows. The newest active row is
     * preferred so pickers remain useful without exposing duplicate choices to users.
     *
     * @param  array<int, array<string, mixed>>  $details
     * @return array<int, array<string, mixed>>
     */
    public function deduplicateProductDetails(array $details): array
    {
        return collect($details)
            ->sortByDesc(fn (array $detail): array => [
                ! array_key_exists('flagActive', $detail)
                    || in_array(mb_strtolower((string) $detail['flagActive']), ['1', 'true', 'yes'], true),
                (int) ($detail['productDetailID'] ?? 0),
            ])
            ->unique(function (array $detail): string {
                $productId = (int) ($detail['productID'] ?? 0);
                $uomId = (int) ($detail['uomID'] ?? $detail['unitID'] ?? 0);
                $unit = mb_strtolower(trim((string) ($detail['unit'] ?? $detail['uomName'] ?? '')));

                return $productId.'|'.($uomId > 0 ? 'uom:'.$uomId : 'unit:'.$unit);
            })
            ->sortBy(fn (array $detail): array => [
                mb_strtolower((string) ($detail['productName'] ?? '')),
                mb_strtolower((string) ($detail['unit'] ?? $detail['uomName'] ?? '')),
            ])
            ->keyBy(fn (array $detail): int => (int) ($detail['productDetailID'] ?? 0))
            ->all();
    }

    /** @param array<int, array<string, mixed>> $details */
    public function filterActiveProductDetails(array $details): array
    {
        $productIds = collect($details)->pluck('productID')->map(fn ($id): int => (int) $id)->filter()->unique();
        $activeIdsByProduct = [];
        $missingPaths = [];

        foreach ($productIds as $productId) {
            $cached = Cache::get("esb.master_product.active_details.{$productId}");
            if (is_array($cached)) {
                $activeIdsByProduct[$productId] = $cached;
            } else {
                $missingPaths[$productId] = '/product/'.$productId;
            }
        }

        $responses = $missingPaths === [] ? [] : $this->client->poolGetPaths($missingPaths);
        foreach ($responses as $productId => $response) {
            if (! $response || $response->failed() || data_get($response->json(), 'status') !== 'ok') {
                continue;
            }

            $activeIds = collect(data_get($response->json(), 'result.productDetails', []))
                ->filter(fn (array $detail): bool => (bool) ($detail['flagActive'] ?? false))
                ->pluck('productDetailID')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $activeIdsByProduct[(int) $productId] = $activeIds;
            Cache::put("esb.master_product.active_details.{$productId}", $activeIds, now()->addHours(6));
        }

        $activeDetails = collect($details)->filter(function (array $detail) use ($activeIdsByProduct): bool {
            $productId = (int) ($detail['productID'] ?? 0);

            return ! isset($activeIdsByProduct[$productId])
                || in_array((int) ($detail['productDetailID'] ?? 0), $activeIdsByProduct[$productId], true);
        })->all();

        return $this->deduplicateProductDetails($activeDetails);
    }

    /** @return array{productID:int,isTemp:bool} */
    public function createProduct(array $payload): array
    {
        $result = $this->client->successfulResult(
            $this->client->request('post', '/product', $payload),
            'membuat Master Product',
        );
        $productId = (int) ($result['productID'] ?? 0);
        if ($productId < 1) {
            throw new RuntimeException('ESB tidak mengembalikan Product ID.');
        }

        return ['productID' => $productId, 'isTemp' => (bool) ($result['isTemp'] ?? false)];
    }

    public function updateProduct(int $productId, array $payload): void
    {
        $this->client->successfulResult(
            $this->client->request('put', '/product/'.$productId, $payload),
            'memperbarui Master Product',
        );
    }

    /**
     * Update a product while replacing, instead of mutating, Product Details whose conversion
     * factor changed. ESB requires the old detail to be inactive before the replacement is made.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $remoteProduct
     * @return array<string, mixed>
     */
    public function updateProductWithUnitReplacements(int $productId, array $payload, array $remoteProduct): array
    {
        $desiredDetails = collect($payload['productDetails'] ?? [])->values();
        $remoteDetails = collect($remoteProduct['productDetails'] ?? [])->values();
        $changedDetailIds = $desiredDetails
            ->filter(function (array $desired) use ($remoteDetails): bool {
                $detailId = (int) ($desired['productDetailID'] ?? 0);
                if ($detailId < 1) {
                    return false;
                }

                $remote = $remoteDetails->firstWhere('productDetailID', $detailId);

                return is_array($remote)
                    && (array_key_exists('qty', $remote) || array_key_exists('conversionFactor', $remote))
                    && abs($this->detailConversionFactor($remote) - $this->detailConversionFactor($desired)) > 0.000001;
            })
            ->pluck('productDetailID')
            ->map(fn ($detailId): int => (int) $detailId)
            ->values();

        if ($changedDetailIds->isEmpty()) {
            $this->updateProduct($productId, $payload);

            return $this->findProductById($productId) ?? [];
        }

        $deactivationPayload = $payload;
        $deactivationPayload['productDetails'] = $remoteDetails
            ->map(fn (array $detail): array => $this->remoteDetailPayload($detail, $changedDetailIds->contains((int) ($detail['productDetailID'] ?? 0))))
            ->all();
        $this->updateProduct($productId, $deactivationPayload);

        $afterDeactivation = $this->findProductById($productId);
        if (! is_array($afterDeactivation)) {
            throw new RuntimeException('Product Detail lama sudah dikirim untuk dinonaktifkan, tetapi hasilnya belum dapat diverifikasi.');
        }

        $stillActive = collect($afterDeactivation['productDetails'] ?? [])->contains(
            fn (array $detail): bool => $changedDetailIds->contains((int) ($detail['productDetailID'] ?? 0))
                && $this->detailIsActive($detail),
        );
        if ($stillActive) {
            throw new RuntimeException('ESB belum menonaktifkan Product Detail lama. Product Detail baru tidak dibuat untuk mencegah duplikasi.');
        }

        $replacementPayload = $payload;
        $replacementDetails = collect($afterDeactivation['productDetails'] ?? [])
            ->map(fn (array $detail): array => $this->remoteDetailPayload($detail))
            ->keyBy(fn (array $detail): int => (int) ($detail['productDetailID'] ?? 0));

        foreach ($desiredDetails as $desired) {
            $detailId = (int) ($desired['productDetailID'] ?? 0);
            if ($changedDetailIds->contains($detailId)) {
                unset($desired['productDetailID']);
                $replacementDetails->push($desired);

                continue;
            }

            if ($detailId > 0) {
                $replacementDetails->put($detailId, $desired);
            } else {
                $replacementDetails->push($desired);
            }
        }

        $replacementPayload['productDetails'] = $replacementDetails->values()->all();
        $this->updateProduct($productId, $replacementPayload);

        $updatedProduct = $this->findProductById($productId);
        if (! is_array($updatedProduct)) {
            throw new RuntimeException('Product Detail baru sudah dikirim, tetapi hasilnya belum dapat direkonsiliasi dari ESB.');
        }

        foreach ($desiredDetails->whereIn('productDetailID', $changedDetailIds) as $desired) {
            $replacementExists = collect($updatedProduct['productDetails'] ?? [])->contains(
                fn (array $detail): bool => $this->detailIsActive($detail)
                    && $this->sameUnit($detail, $desired)
                    && abs($this->detailConversionFactor($detail) - $this->detailConversionFactor($desired)) <= 0.000001,
            );
            if (! $replacementExists) {
                throw new RuntimeException('ESB belum mengembalikan Product Detail baru dengan conversion factor yang diminta.');
            }
        }

        return $updatedProduct;
    }

    /** @return array<string, mixed> */
    private function remoteDetailPayload(array $detail, bool $deactivate = false): array
    {
        return [
            'productDetailID' => (int) ($detail['productDetailID'] ?? 0),
            'uomID' => (int) ($detail['uomID'] ?? $detail['unitID'] ?? 0),
            'qty' => $this->detailConversionFactor($detail),
            'basePrice' => (float) ($detail['basePrice'] ?? 0),
            'sku' => (string) ($detail['sku'] ?? $detail['SKU'] ?? ''),
            'cubication' => (float) ($detail['cubication'] ?? 0),
            'weight' => (float) ($detail['weight'] ?? 0),
            'isBase' => (bool) ($detail['isBase'] ?? false),
            'isStock' => $deactivate ? false : (bool) ($detail['isStock'] ?? false),
            'isPurchase' => $deactivate ? false : (bool) ($detail['isPurchase'] ?? false),
            'isTransfer' => $deactivate ? false : (bool) ($detail['isTransfer'] ?? false),
            'isSales' => $deactivate ? false : (bool) ($detail['isSales'] ?? false),
            'menuID' => $detail['menuID'] ?? null,
            'flagActive' => $deactivate ? false : $this->detailIsActive($detail),
        ];
    }

    private function detailConversionFactor(array $detail): float
    {
        return (float) ($detail['qty'] ?? $detail['conversionFactor'] ?? 0);
    }

    private function detailIsActive(array $detail): bool
    {
        return ! array_key_exists('flagActive', $detail)
            || in_array(mb_strtolower((string) $detail['flagActive']), ['1', 'true', 'yes'], true);
    }

    private function sameUnit(array $left, array $right): bool
    {
        $leftUomId = (int) ($left['uomID'] ?? $left['unitID'] ?? 0);
        $rightUomId = (int) ($right['uomID'] ?? $right['unitID'] ?? 0);

        return $leftUomId > 0 && $leftUomId === $rightUomId;
    }

    /** @return array{categories:array<int,string>,subCategories:array<int,string>} */
    public function getProductTaxonomy(): array
    {
        return Cache::remember('esb_core.product_taxonomy', now()->addHours(6), function (): array {
            $first = $this->getProducts(['page' => 1, 'limit' => 100]);
            $rows = $first['data'];
            $lastPage = max(1, (int) ceil($first['count'] / max(1, $first['limit'])));

            foreach (array_chunk($lastPage > 1 ? range(2, $lastPage) : [], 10) as $pages) {
                $queries = collect($pages)->mapWithKeys(fn (int $page): array => [(string) $page => [
                    'page' => $page, 'limit' => 100, 'flagActive' => 1,
                ]])->all();
                foreach ($this->client->poolGet('/product/list', $queries) as $response) {
                    if ($response->failed()) {
                        continue;
                    }
                    $pageRows = data_get($response->json(), 'result.data', []);
                    if (is_array($pageRows)) {
                        array_push($rows, ...$pageRows);
                    }
                }
            }

            $categories = [];
            $subCategories = [];
            foreach ($rows as $product) {
                $categoryId = (int) ($product['categoryID'] ?? 0);
                $categoryName = (string) ($product['categoryName'] ?? $product['categoryNameCategory'] ?? '');
                if ($categoryId > 0 && $categoryName !== '') {
                    $categories[$categoryId] = $categoryName;
                }
                $subCategoryId = (int) ($product['subCategoryID'] ?? 0);
                $subCategoryName = (string) ($product['subCategoryName'] ?? '');
                if ($subCategoryId > 0 && $subCategoryName !== '') {
                    $subCategories[$subCategoryId] = $subCategoryName;
                }
            }
            asort($categories, SORT_NATURAL | SORT_FLAG_CASE);
            asort($subCategories, SORT_NATURAL | SORT_FLAG_CASE);

            return compact('categories', 'subCategories');
        });
    }

    public function suggestNextProductCode(int $categoryId): ?string
    {
        if ($categoryId < 1) {
            return null;
        }
        $codes = [];
        foreach ([1, 0] as $flagActive) {
            $page = 1;
            do {
                $result = $this->getProducts([
                    'page' => $page,
                    'limit' => 100,
                    'categoryID' => $categoryId,
                    'flagActive' => $flagActive,
                ]);
                foreach ($result['data'] as $product) {
                    if (($code = trim((string) ($product['productCode'] ?? ''))) !== '') {
                        $codes[] = $code;
                    }
                }
                $hasNext = filled($result['next']) || (($result['page'] * $result['limit']) < $result['count']);
                $page++;
            } while ($hasNext && $page <= 100);
        }

        $sequences = collect($codes)->unique()->map(function (string $code): ?array {
            if (! preg_match('/^(.*?)(\d+)$/', $code, $matches)) {
                return null;
            }

            return ['prefix' => $matches[1], 'number' => (int) $matches[2], 'padding' => strlen($matches[2])];
        })->filter()->groupBy('prefix')->sortByDesc(fn ($items): int => $items->count());
        $sequence = $sequences->first();
        if (! $sequence) {
            return null;
        }
        $numbers = $sequence->pluck('number')->unique()->sortDesc()->values();
        $candidateNumber = $numbers->first(fn (int $number): bool => $numbers->count() < 4 || $numbers
            ->filter(fn (int $other): bool => $other < $number && $other >= ($number - 20))->count() >= 3) ?? $numbers->first();
        $candidate = $sequence->first(fn (array $item): bool => $item['number'] === $candidateNumber);

        return is_array($candidate)
            ? $candidate['prefix'].str_pad((string) ($candidate['number'] + 1), $candidate['padding'], '0', STR_PAD_LEFT)
            : null;
    }
}
