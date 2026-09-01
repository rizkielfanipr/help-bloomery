<?php

namespace App\Services;

use App\Models\BulkProductSubmission;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EsbCompanyProductService
{
    /** @return array{categories:array<int,string>,subCategoriesByCategory:array<int,array<int,string>>,productCodesByCategory:array<int,array<int,string>>} */
    public function taxonomy(string $comcode): array
    {
        $this->ensureSupported($comcode);

        return Cache::remember($this->taxonomyCacheKey($comcode), now()->addHours(6), function () use ($comcode): array {
            $rows = [];
            $page = 1;

            do {
                $result = $this->successfulResult(
                    $this->request($comcode, 'get', '/product/list', ['page' => $page, 'limit' => 100, 'flagActive' => 1]),
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
        $result = $this->successfulResult($this->request($comcode, 'post', '/product', $payload), 'membuat produk');
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
            $this->request($comcode, 'put', '/product/'.$productId, $payload),
            'memperbarui produk',
        );
    }

    private function taxonomyCacheKey(string $comcode): string
    {
        return 'esb_core.product_taxonomy.v3.'.$comcode;
    }

    private function request(string $comcode, string $method, string $path, array $payload): Response
    {
        $this->ensureSupported($comcode);
        $response = $this->send($method, $path, $payload, $this->accessToken($comcode));

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey($comcode));
            $response = $this->send($method, $path, $payload, $this->accessToken($comcode));
        }

        return $response;
    }

    private function send(string $method, string $path, array $payload, string $token): Response
    {
        $request = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout(10)
            ->timeout((int) config('esb.core.timeout', 60));

        return match ($method) {
            'get' => $request->get($this->baseUrl().$path, $payload),
            'put' => $request->put($this->baseUrl().$path, $payload),
            default => $request->post($this->baseUrl().$path, $payload),
        };
    }

    private function accessToken(string $comcode): string
    {
        $cached = Cache::get($this->tokenCacheKey($comcode));
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return Cache::lock('esb_core.login_lock.'.$comcode, 15)->block(10, function () use ($comcode): string {
            $cached = Cache::get($this->tokenCacheKey($comcode));
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $username = (string) config("esb.core.companies.{$comcode}.username");
            $password = (string) config("esb.core.companies.{$comcode}.password");
            if ($username === '' || $password === '') {
                throw new RuntimeException("Credential ESB Core {$comcode} belum dikonfigurasi.");
            }

            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout((int) config('esb.core.timeout', 60))
                ->post($this->baseUrl().'/auth/login', [
                    'username' => $username,
                    'password' => $password,
                ]);
            $payload = $response->json();
            $token = is_array($payload) ? (string) data_get($payload, 'result.accessToken', '') : '';

            if ($response->failed() || $token === '') {
                throw new RuntimeException($this->errorMessage($response, "login ke ESB Core {$comcode}"));
            }

            Cache::put($this->tokenCacheKey($comcode), $token, max(60, (int) config('esb.core.token_ttl', 3300)));

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
        $messages = collect(is_array($payload) ? ($payload['errors'] ?? []) : [])->pluck('message')->filter();
        $detail = $messages->isNotEmpty() ? $messages->implode('; ') : (is_array($payload) ? ($payload['message'] ?? null) : null);

        return 'Gagal '.$action.($detail ? ': '.$detail : ' (HTTP '.$response->status().').');
    }

    private function ensureSupported(string $comcode): void
    {
        if (! in_array($comcode, BulkProductSubmission::COMCODES, true)) {
            throw new RuntimeException("Comcode {$comcode} tidak didukung.");
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('esb.core.base_url'), '/');
    }

    private function tokenCacheKey(string $comcode): string
    {
        return 'esb_core.access_token.'.$comcode;
    }
}
