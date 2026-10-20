<?php

namespace App\Services;

use App\Models\BulkProductSubmission;
use App\Services\Monitoring\EsbAttempt;
use App\Services\Monitoring\EsbMutation;
use App\Services\Monitoring\RecordsEsbMutations;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EsbCompanyProductService
{
    use RecordsEsbMutations;

    public function __construct(private readonly EsbCoreClient $client) {}

    /** @return array{categories:array<int,string>,subCategoriesByCategory:array<int,array<int,string>>,productCodesByCategory:array<int,array<int,string>>} */
    public function taxonomy(string $comcode): array
    {
        $this->ensureSupported($comcode);

        return Cache::remember($this->taxonomyCacheKey($comcode), now()->addHours(6), function () use ($comcode): array {
            $activeRows = $this->productRows($comcode, 1);
            $codeRows = [...$activeRows, ...$this->productRows($comcode, 0)];

            $categories = [];
            $subCategoriesByCategory = [];
            $productCodesByCategory = [];
            foreach ($activeRows as $product) {
                $categoryId = (int) ($product['categoryID'] ?? 0);
                $categoryName = trim((string) ($product['categoryName'] ?? $product['categoryNameCategory'] ?? ''));
                $subCategoryId = (int) ($product['subCategoryID'] ?? 0);
                $subCategoryName = trim((string) ($product['subCategoryName'] ?? ''));

                if ($categoryId > 0 && $categoryName !== '') {
                    $categories[$categoryId] = $categoryName;
                }
                if ($categoryId > 0 && $subCategoryId > 0 && $subCategoryName !== '') {
                    $subCategoriesByCategory[$categoryId][$subCategoryId] = $subCategoryName;
                }
            }

            foreach ($codeRows as $product) {
                $categoryId = (int) ($product['categoryID'] ?? 0);
                $productCode = trim((string) ($product['productCode'] ?? ''));
                if ($categoryId > 0 && $productCode !== '') {
                    $productCodesByCategory[$categoryId][] = $productCode;
                }
            }

            asort($categories, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($subCategoriesByCategory as &$subCategories) {
                asort($subCategories, SORT_NATURAL | SORT_FLAG_CASE);
            }
            foreach ($productCodesByCategory as &$productCodes) {
                $productCodes = array_values(array_unique($productCodes));
            }

            return [
                'categories' => $categories,
                'subCategoriesByCategory' => $subCategoriesByCategory,
                'productCodesByCategory' => $productCodesByCategory,
            ];
        });
    }

    public function suggestNextProductCode(string $comcode, int $categoryId): ?string
    {
        $codes = collect($this->taxonomy($comcode)['productCodesByCategory'][$categoryId] ?? []);
        $sequences = $codes
            ->map(function (string $code): ?array {
                if (! preg_match('/^(.*?)(\d+)$/', $code, $matches)) {
                    return null;
                }

                return [
                    'prefix' => $matches[1],
                    'number' => (int) $matches[2],
                    'padding' => strlen($matches[2]),
                ];
            })
            ->filter()
            ->groupBy('prefix')
            ->sortByDesc(fn ($items): int => $items->count());

        $sequence = $sequences->first();
        if (! $sequence) {
            return null;
        }

        $numbers = $sequence
            ->pluck('number')
            ->unique()
            ->sortDesc()
            ->values();

        $candidateNumber = $numbers->first(function (int $number) use ($numbers): bool {
            if ($numbers->count() < 4) {
                return true;
            }

            return $numbers
                ->filter(fn (int $other): bool => $other < $number && $other >= ($number - 20))
                ->count() >= 3;
        }) ?? $numbers->first();

        $latest = $sequence->first(
            fn (array $item): bool => $item['number'] === $candidateNumber,
        );

        if (! is_array($latest)) {
            return null;
        }

        return $latest['prefix'].str_pad(
            (string) ($latest['number'] + 1),
            $latest['padding'],
            '0',
            STR_PAD_LEFT,
        );
    }

    /** @return array<string, mixed>|null */
    public function detailByProductDetailId(string $comcode, int $productDetailId): ?array
    {
        if ($productDetailId < 1) {
            return null;
        }

        $result = $this->successfulResult(
            $comcode,
            'get',
            '/product/list',
            ['page' => 1, 'limit' => 100, 'productDetailID' => $productDetailId, 'flagActive' => 1],
            'mengambil detail produk',
        );

        foreach ((array) ($result['data'] ?? []) as $product) {
            foreach ((array) ($product['productDetails'] ?? []) as $detail) {
                if ((int) ($detail['productDetailID'] ?? 0) === $productDetailId) {
                    return $product + ['matchedProductDetail' => $detail];
                }
            }

            if ((int) ($product['productDetailID'] ?? 0) === $productDetailId) {
                return $product;
            }
        }

        return null;
    }

    /** @return array{productID:int,isTemp:bool} */
    public function create(string $comcode, array $payload): array
    {
        $this->ensureSupported($comcode);

        $created = $this->recordEsbMutation(
            new EsbMutation('POST', '/product', 'create_product', 'master_product', $payload, $comcode),
            function (EsbAttempt $attempt) use ($comcode, $payload): array {
                $result = $this->successfulResult($comcode, 'post', '/product', $payload, 'membuat produk');
                $productId = (int) ($result['productID'] ?? 0);

                if ($productId < 1) {
                    throw new RuntimeException("ESB {$comcode} tidak mengembalikan Product ID.");
                }
                $attempt->reference($productId);

                return ['productID' => $productId, 'isTemp' => (bool) ($result['isTemp'] ?? false)];
            },
        );

        Cache::forget($this->taxonomyCacheKey($comcode));

        return $created;
    }

    public function update(string $comcode, int $productId, array $payload): void
    {
        $this->ensureSupported($comcode);

        $this->recordEsbMutation(
            new EsbMutation('PUT', '/product/{productID}', 'update_product', 'master_product', $payload, $comcode, externalReference: (string) $productId),
            fn () => $this->successfulResult(
                $comcode,
                'put',
                '/product/'.$productId,
                $payload,
                'memperbarui produk',
            ),
        );
    }

    /**
     * Master Product operations bound to one company, so multi-company updates use the same
     * canonical Product Detail replacement flow as R&D Product Release instead of changing the
     * conversion factor of an existing Product Detail ID.
     */
    public function masterProducts(string $comcode): EsbMasterProductService
    {
        $this->ensureSupported($comcode);

        return new EsbMasterProductService(new EsbCompanyBomClient($this->client, $comcode));
    }

    private function taxonomyCacheKey(string $comcode): string
    {
        return 'esb_core.product_taxonomy.v4.'.$comcode;
    }

    /** @return array<int, array<string, mixed>> */
    private function productRows(string $comcode, int $flagActive): array
    {
        $rows = [];
        $page = 1;

        do {
            $result = $this->successfulResult(
                $comcode,
                'get',
                '/product/list',
                ['page' => $page, 'limit' => 100, 'flagActive' => $flagActive],
                "mengambil kategori produk {$comcode}",
            );
            array_push($rows, ...(is_array($result['data'] ?? null) ? $result['data'] : []));
            $count = (int) ($result['count'] ?? count($rows));
            $limit = max(1, (int) ($result['limit'] ?? 100));
            $hasNext = filled($result['next'] ?? null) || ($page * $limit) < $count;
            $page++;
        } while ($hasNext && $page <= 100);

        return $rows;
    }

    private function successfulResult(string $comcode, string $method, string $path, array $payload, string $action): array
    {
        $this->ensureSupported($comcode);
        $response = $this->client->request($comcode, $method, $path, $payload);

        return $this->client->successfulResult($response, $action, $comcode, $path);
    }

    private function ensureSupported(string $comcode): void
    {
        if (! in_array($comcode, BulkProductSubmission::COMCODES, true)) {
            throw new RuntimeException("Comcode {$comcode} tidak didukung.");
        }
    }
}
