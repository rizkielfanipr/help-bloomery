<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EsbCoreService
{
    private const TOKEN_CACHE_KEY = 'esb_core.access_token';

    private string $baseUrl;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('esb.core.base_url'), '/');
        $this->timeout = (int) config('esb.core.timeout', 60);
    }

    /**
     * @return array{page:int, limit:int, count:int, data:array<int, mixed>, prev:?string, next:?string}
     */
    public function getBillOfMaterials(array $filters = []): array
    {
        $response = $this->request('get', '/product/bom', array_filter([
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'limit' => min(5000, max(1, (int) ($filters['limit'] ?? 20))),
            'bomID' => $filters['bomID'] ?? null,
            'productName' => $filters['productName'] ?? null,
            'uomName' => $filters['uomName'] ?? null,
            'sort' => $filters['sort'] ?? null,
            'flagActive' => $filters['flagActive'] ?? 1,
        ], fn ($value) => $value !== null && $value !== ''));

        $result = $this->successfulResult($response, 'mengambil daftar Bill of Material');

        return [
            'page' => (int) ($result['page'] ?? 1),
            'limit' => (int) ($result['limit'] ?? 20),
            'count' => (int) ($result['count'] ?? 0),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'prev' => $result['prev'] ?: null,
            'next' => $result['next'] ?: null,
        ];
    }

    public function createAssembly(array $payload): int
    {
        $payload['bomTypeID'] = 1;

        $response = $this->request('post', '/product/bom', $payload);
        $result = $this->successfulResult($response, 'membuat Bill of Material');
        $bomId = (int) ($result['bomID'] ?? 0);

        if ($bomId < 1) {
            throw new RuntimeException('ESB tidak mengembalikan ID Bill of Material.');
        }

        return $bomId;
    }

    /**
     * @return array{productID:int, isTemp:bool}
     */
    public function createProduct(array $payload): array
    {
        $response = $this->request('post', '/product', $payload);
        $result = $this->successfulResult($response, 'membuat Master Product');
        $productId = (int) ($result['productID'] ?? 0);

        if ($productId < 1) {
            throw new RuntimeException('ESB tidak mengembalikan Product ID.');
        }

        return [
            'productID' => $productId,
            'isTemp' => (bool) ($result['isTemp'] ?? false),
        ];
    }

    public function getBillOfMaterial(int $bomId): array
    {
        $response = $this->request('get', '/product/bom/'.$bomId, []);

        return $this->successfulResult($response, 'mengambil detail Bill of Material');
    }

    /**
     * @return array{page:int, limit:int, count:int, data:array<int, mixed>, prev:?string, next:?string}
     */
    public function getPurchaseOrders(array $filters = []): array
    {
        $response = $this->request('get', '/purchase/purchase-order', array_filter([
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($filters['limit'] ?? 100))),
            'sort' => $filters['sort'] ?? '-purchaseDate',
            'purchaseNum' => $filters['purchaseNum'] ?? null,
            'branchID' => $filters['branchID'] ?? null,
            'supplierID' => $filters['supplierID'] ?? null,
            'statusID' => $filters['statusID'] ?? null,
            'dateFrom' => $filters['dateFrom'] ?? null,
            'dateTo' => $filters['dateTo'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $result = $this->successfulResult($response, 'mengambil daftar Purchase Order');

        return [
            'page' => (int) ($result['page'] ?? 1),
            'limit' => (int) ($result['limit'] ?? 100),
            'count' => (int) ($result['count'] ?? 0),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'prev' => ($result['prev'] ?? null) ?: null,
            'next' => ($result['next'] ?? null) ?: null,
        ];
    }

    public function getPurchaseOrder(string $purchaseNum): array
    {
        $response = $this->request('get', '/purchase/purchase-order/'.rawurlencode($purchaseNum), []);

        return $this->successfulResult($response, 'mengambil detail Purchase Order '.$purchaseNum);
    }

    public function updateBillOfMaterial(int $bomId, array $payload): void
    {
        $response = $this->request('put', '/product/bom/'.$bomId, $payload);
        $this->successfulResult($response, 'memperbarui Bill of Material');
    }

    /**
     * @return array<int, array>
     */
    public function getAllBillOfMaterials(): array
    {
        $all = [];
        $page = 1;

        do {
            $result = $this->getBillOfMaterials(['page' => $page, 'limit' => 100]);
            array_push($all, ...$result['data']);
            $page++;
            $hasNext = filled($result['next'])
                || (($result['page'] * $result['limit']) < $result['count']);
        } while ($hasNext && $page <= 100);

        return $all;
    }

    /**
     * @return array{page:int, limit:int, count:int, data:array<int, mixed>, prev:?string, next:?string}
     */
    public function getProducts(array $filters = []): array
    {
        $response = $this->request('get', '/product/list', array_filter([
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($filters['limit'] ?? 20))),
            'productName' => $filters['productName'] ?? null,
            'productCode' => $filters['productCode'] ?? null,
            'categoryID' => $filters['categoryID'] ?? null,
            'subCategoryID' => $filters['subCategoryID'] ?? null,
            'flagActive' => 1,
        ], fn ($value) => $value !== null && $value !== ''));

        $result = $this->successfulResult($response, 'mengambil daftar produk');

        return [
            'page' => (int) ($result['page'] ?? 1),
            'limit' => (int) ($result['limit'] ?? 20),
            'count' => (int) ($result['count'] ?? 0),
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'prev' => $result['prev'] ?: null,
            'next' => $result['next'] ?: null,
        ];
    }

    /**
     * Return every active category and subcategory from Product List.
     *
     * @return array{categories:array<int, string>, subCategories:array<int, string>}
     */
    public function getProductTaxonomy(): array
    {
        return Cache::remember('esb_core.product_taxonomy', now()->addHours(6), function (): array {
            $first = $this->getProducts(['page' => 1, 'limit' => 100]);
            $rows = $first['data'];
            $lastPage = max(1, (int) ceil($first['count'] / max(1, $first['limit'])));
            $token = $this->accessToken();

            $remainingPages = $lastPage > 1 ? range(2, $lastPage) : [];

            foreach (array_chunk($remainingPages, 10) as $pages) {
                $responses = Http::pool(fn ($pool): array => array_map(
                    fn (int $page) => $pool
                        ->as((string) $page)
                        ->withToken($token)
                        ->acceptJson()
                        ->timeout($this->timeout)
                        ->get($this->baseUrl.'/product/list', [
                            'page' => $page,
                            'limit' => 100,
                            'flagActive' => 1,
                        ]),
                    $pages,
                ));

                foreach ($responses as $response) {
                    if (! $response || $response->failed()) {
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
                $categoryName = (string) (
                    $product['categoryName']
                    ?? $product['categoryNameCategory']
                    ?? ''
                );

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

            return [
                'categories' => $categories,
                'subCategories' => $subCategories,
            ];
        });
    }

    /**
     * Suggest the next code by incrementing the largest numeric suffix used in
     * an active product code for the selected category.
     */
    public function suggestNextProductCode(int $categoryId): ?string
    {
        if ($categoryId < 1) {
            return null;
        }

        $codes = [];
        $page = 1;

        do {
            $result = $this->getProducts([
                'page' => $page,
                'limit' => 100,
                'categoryID' => $categoryId,
            ]);

            foreach ($result['data'] as $product) {
                $code = trim((string) ($product['productCode'] ?? ''));
                if ($code !== '') {
                    $codes[] = $code;
                }
            }

            $hasNext = filled($result['next'])
                || (($result['page'] * $result['limit']) < $result['count']);
            $page++;
        } while ($hasNext && $page <= 100);

        $sequences = collect($codes)
            ->map(function (string $code): ?array {
                if (! preg_match('/^(.*?)(\d+)$/', $code, $matches)) {
                    return null;
                }

                return [
                    'prefix' => $matches[1],
                    'number' => (int) $matches[2],
                    'padding' => strlen($matches[2]),
                    'code' => $code,
                ];
            })
            ->filter()
            ->groupBy('prefix')
            ->sortByDesc(fn ($items): int => $items->count());

        $dominantSequence = $sequences->first();
        if (! $dominantSequence) {
            return null;
        }

        $numbers = $dominantSequence
            ->pluck('number')
            ->unique()
            ->sortDesc()
            ->values();

        // Ignore isolated outliers such as BW11203 among the continuous
        // BW0001–BW1300 sequence. A valid latest number should have at least
        // three nearby predecessors in the previous 20 numbers.
        $candidateNumber = $numbers->first(function (int $number) use ($numbers): bool {
            if ($numbers->count() < 4) {
                return true;
            }

            return $numbers
                ->filter(fn (int $other): bool => $other < $number && $other >= ($number - 20))
                ->count() >= 3;
        }) ?? $numbers->first();

        $candidate = $dominantSequence
            ->first(fn (array $item): bool => $item['number'] === $candidateNumber);

        if (! is_array($candidate)) {
            return null;
        }

        return $candidate['prefix'].str_pad(
            (string) ($candidate['number'] + 1),
            $candidate['padding'],
            '0',
            STR_PAD_LEFT,
        );
    }

    private function request(string $method, string $path, array $data): Response
    {
        $response = $this->send($method, $path, $data, $this->accessToken());

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->send($method, $path, $data, $this->accessToken());
        }

        return $response;
    }

    private function send(string $method, string $path, array $data, string $token): Response
    {
        $request = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout($this->timeout);

        return match ($method) {
            'get' => $request->get($this->baseUrl.$path, $data),
            'put' => $request->put($this->baseUrl.$path, $data),
            default => $request->post($this->baseUrl.$path, $data),
        };
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return Cache::lock('esb_core.login_lock', 15)->block(10, function (): string {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $username = (string) config('esb.core.username');
            $password = (string) config('esb.core.password');

            if ($this->baseUrl === '' || $username === '' || $password === '') {
                throw new RuntimeException('Konfigurasi koneksi ESB Core belum lengkap.');
            }

            $response = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post($this->baseUrl.'/auth/login', [
                    'username' => $username,
                    'password' => $password,
                ]);

            $payload = $response->json();
            $token = is_array($payload) ? (string) data_get($payload, 'result.accessToken', '') : '';

            if ($response->failed() || $token === '') {
                throw new RuntimeException($this->errorMessage($response, 'login ke ESB Core'));
            }

            Cache::put(
                self::TOKEN_CACHE_KEY,
                $token,
                max(60, (int) config('esb.core.token_ttl', 3300)),
            );

            return $token;
        });
    }

    private function successfulResult(Response $response, string $action): array
    {
        $payload = $response->json();

        if ($response->failed() || ! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
            throw new RuntimeException($this->errorMessage($response, $action));
        }

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    private function errorMessage(Response $response, string $action): string
    {
        $payload = $response->json();
        $messages = collect(is_array($payload) ? ($payload['errors'] ?? []) : [])
            ->pluck('message')
            ->filter()
            ->values();

        $message = is_array($payload) ? ($payload['message'] ?? null) : null;
        $detail = $messages->isNotEmpty() ? $messages->implode('; ') : $message;

        return 'Gagal '.$action.($detail ? ': '.$detail : ' (HTTP '.$response->status().').');
    }
}
