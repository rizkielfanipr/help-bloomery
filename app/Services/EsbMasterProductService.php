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
                'flagActive' => 1,
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
        return collect($this->getAllProducts())->first(
            fn (array $product): bool => (int) ($product['productID'] ?? 0) === $productId,
        );
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
        $page = 1;
        do {
            $result = $this->getProducts(['page' => $page, 'limit' => 100, 'categoryID' => $categoryId]);
            foreach ($result['data'] as $product) {
                if (($code = trim((string) ($product['productCode'] ?? ''))) !== '') {
                    $codes[] = $code;
                }
            }
            $hasNext = filled($result['next']) || (($result['page'] * $result['limit']) < $result['count']);
            $page++;
        } while ($hasNext && $page <= 100);

        $sequences = collect($codes)->map(function (string $code): ?array {
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
