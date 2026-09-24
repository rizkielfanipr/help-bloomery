<?php

namespace App\Services;

use App\Models\BulkProductSubmission;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EsbCompanyProductService
{
    public function __construct(private readonly EsbCoreClient $client) {}

    /** @return array{categories:array<int,string>,subCategoriesByCategory:array<int,array<int,string>>,productCodesByCategory:array<int,array<int,string>>} */
    public function taxonomy(string $comcode): array
    {
        $this->ensureSupported($comcode);

        return Cache::remember($this->taxonomyCacheKey($comcode), now()->addHours(6), function () use ($comcode): array {
            $rows = [];
            $page = 1;

            do {
                $result = $this->successfulResult(
                    $comcode,
                    'get',
                    '/product/list',
                    ['page' => $page, 'limit' => 100, 'flagActive' => 1],
                    "mengambil kategori produk {$comcode}",
                );
                array_push($rows, ...(is_array($result['data'] ?? null) ? $result['data'] : []));
                $count = (int) ($result['count'] ?? count($rows));
                $limit = max(1, (int) ($result['limit'] ?? 100));
                $hasNext = filled($result['next'] ?? null) || ($page * $limit) < $count;
                $page++;
            } while ($hasNext && $page <= 100);

            $categories = [];
            $subCategoriesByCategory = [];
            $productCodesByCategory = [];
            foreach ($rows as $product) {
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
                $productCode = trim((string) ($product['productCode'] ?? ''));
                if ($categoryId > 0 && $productCode !== '') {
                    $productCodesByCategory[$categoryId][] = $productCode;
                }
            }

            asort($categories, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($subCategoriesByCategory as &$subCategories) {
                asort($subCategories, SORT_NATURAL | SORT_FLAG_CASE);
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

    /** @return array{productID:int,isTemp:bool} */
    public function create(string $comcode, array $payload): array
    {
        $result = $this->successfulResult($comcode, 'post', '/product', $payload, 'membuat produk');
        $productId = (int) ($result['productID'] ?? 0);

        if ($productId < 1) {
            throw new RuntimeException("ESB {$comcode} tidak mengembalikan Product ID.");
        }

        Cache::forget($this->taxonomyCacheKey($comcode));

        return ['productID' => $productId, 'isTemp' => (bool) ($result['isTemp'] ?? false)];
    }

    public function update(string $comcode, int $productId, array $payload): void
    {
        $this->successfulResult(
            $comcode,
            'put',
            '/product/'.$productId,
            $payload,
            'memperbarui produk',
        );
    }

    private function taxonomyCacheKey(string $comcode): string
    {
        return 'esb_core.product_taxonomy.v3.'.$comcode;
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
