<?php

namespace App\Actions\Rnd\ShelfLife;

use App\Models\RndProductEsbShelfLife;
use App\Models\RndWipProduct;
use App\Services\EsbCoreClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pulls every active ESB Product of the company, keeps only category "Barang WIP", and upserts
 * one `rnd_wip_products` row per active Product Detail (unit). Read-only towards ESB.
 *
 * `/product/list` does not carry Product Details, so each WIP Product's units are read from
 * `/product/{id}` in concurrent batches. The base unit (`isBase`) is the row the Shelf Life menu
 * lists. Rows no longer returned are only flagged inactive, and only after a run in which every
 * page and every Product detail was read, so a partial failure never hides WIPs.
 */
class SyncWipProductCatalogAction
{
    public const WIP_CATEGORY = 'barang wip';

    private const LOCK_KEY = 'rnd_wip_product_catalog_sync';

    private const PROGRESS_CACHE_KEY = 'rnd_wip_product_catalog_sync.progress';

    private const MAX_PAGES = 200;

    private const DETAIL_CONCURRENCY = 25;

    public function __construct(private readonly EsbCoreClient $client) {}

    /** @return array{status: string, scanned?: int, products?: int, synced?: int, failed?: int, deactivated?: int} */
    public function execute(?int $triggeredBy = null, string $companyCode = RndProductEsbShelfLife::DEFAULT_COMPANY_CODE): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 1800);

        if (! $lock->get()) {
            return ['status' => 'already_running'];
        }

        try {
            return $this->sync($triggeredBy, $companyCode);
        } catch (Throwable $exception) {
            $this->putProgress(['status' => 'failed', 'triggered_by' => $triggeredBy, 'error' => $exception->getMessage(), 'finished_at' => now()->toIso8601String()]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed>|null */
    public static function progress(): ?array
    {
        return Cache::get(self::PROGRESS_CACHE_KEY);
    }

    /** @param  array<string, mixed>  $product */
    public static function isWipCategory(array $product): bool
    {
        $category = mb_strtolower(trim((string) ($product['categoryName'] ?? $product['categoryNameCategory'] ?? '')));

        return $category === self::WIP_CATEGORY;
    }

    /** @return array{status: string, scanned: int, products: int, synced: int, failed: int, deactivated: int} */
    private function sync(?int $triggeredBy, string $companyCode): array
    {
        $syncedAt = now()->startOfSecond();
        $progress = ['status' => 'running', 'triggered_by' => $triggeredBy, 'started_at' => $syncedAt->toIso8601String()];
        $wipProducts = $this->wipProductsFromList($companyCode, $progress);

        $synced = 0;
        $failed = 0;
        $errors = [];
        $done = 0;

        foreach (array_chunk($wipProducts, self::DETAIL_CONCURRENCY, true) as $batch) {
            $needDetail = array_filter($batch, fn (array $product): bool => ! is_array($product['productDetails'] ?? null));
            $responses = $needDetail === [] ? [] : $this->client->poolGetPaths(
                $companyCode,
                array_map(fn (array $product): string => '/product/'.(int) $product['productID'], $needDetail),
            );

            $rows = [];
            foreach ($batch as $key => $product) {
                if (array_key_exists($key, $needDetail)) {
                    $detail = $this->detailResult($responses[$key] ?? null);
                    if ($detail === null) {
                        $failed++;
                        $errors[] = ($product['productCode'] ?? 'Produk '.$product['productID']).': detail tidak dapat dibaca';

                        continue;
                    }
                    $product['productDetails'] = $detail['productDetails'] ?? [];
                }

                array_push($rows, ...$this->detailRows($product, $companyCode, $syncedAt));
            }

            if ($rows !== []) {
                RndWipProduct::query()->upsert(
                    $rows,
                    ['company_code', 'product_detail_id'],
                    ['esb_product_id', 'product_code', 'product_name', 'uom_name', 'is_base', 'category_name', 'is_active', 'last_synced_at', 'updated_at'],
                );
                $synced += count($rows);
            }

            $done += count($batch);
            $this->putProgress([...$progress, 'phase' => 'details', 'scanned' => $done, 'total' => count($wipProducts), 'synced' => $synced, 'failed' => $failed]);
        }

        // A partial run must not hide WIPs whose detail could not be read.
        $deactivated = $failed > 0 ? 0 : RndWipProduct::query()
            ->where('company_code', $companyCode)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $syncedAt))
            ->update(['is_active' => false, 'updated_at' => now()]);

        $this->putProgress([
            ...$progress,
            'status' => 'completed',
            'scanned' => count($wipProducts),
            'total' => count($wipProducts),
            'products' => count($wipProducts) - $failed,
            'synced' => $synced,
            'failed' => $failed,
            'deactivated' => $deactivated,
            'errors' => array_slice($errors, 0, 20),
            'finished_at' => now()->toIso8601String(),
        ]);

        return ['status' => 'completed', 'scanned' => count($wipProducts), 'products' => count($wipProducts) - $failed, 'synced' => $synced, 'failed' => $failed, 'deactivated' => $deactivated];
    }

    /**
     * Every active list row of category "Barang WIP", keyed by ESB Product ID.
     *
     * @param  array<string, mixed>  $progress
     * @return array<int, array<string, mixed>>
     */
    private function wipProductsFromList(string $companyCode, array $progress): array
    {
        $page = 1;
        $scanned = 0;
        $wipProducts = [];

        do {
            $result = $this->client->successfulResult(
                $this->client->request($companyCode, 'get', '/product/list', ['page' => $page, 'limit' => 100, 'flagActive' => 1]),
                'mengambil daftar produk WIP',
                $companyCode,
                '/product/list',
            );
            $products = is_array($result['data'] ?? null) ? $result['data'] : [];
            $scanned += count($products);

            foreach ($products as $product) {
                if (is_array($product) && self::isWipCategory($product) && (int) ($product['productID'] ?? 0) > 0) {
                    $wipProducts[(int) $product['productID']] = $product;
                }
            }

            $this->putProgress([...$progress, 'phase' => 'list', 'scanned' => $scanned, 'total' => (int) ($result['count'] ?? $scanned), 'synced' => 0]);

            $limit = max(1, (int) ($result['limit'] ?? 100));
            $hasNext = filled($result['next'] ?? null) || ($page * $limit) < (int) ($result['count'] ?? 0);
            $page++;
        } while ($hasNext && $page <= self::MAX_PAGES);

        return $wipProducts;
    }

    /** @return array<string, mixed>|null */
    private function detailResult(mixed $response): ?array
    {
        if ($response === null || $response->failed()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload) || ($payload['status'] ?? null) !== 'ok' || ! is_array($payload['result'] ?? null)) {
            return null;
        }

        return $payload['result'];
    }

    /**
     * One row per Product Detail. The active `isBase` unit (or the first active one) is the base;
     * inactive units are kept as non-base aliases because existing BOMs still use them (e.g. an
     * old "Resep" unit). A Product without any active unit is skipped.
     *
     * @param  array<string, mixed>  $product
     * @return list<array<string, mixed>>
     */
    private function detailRows(array $product, string $companyCode, Carbon $syncedAt): array
    {
        $details = collect(is_array($product['productDetails'] ?? null) ? $product['productDetails'] : [])
            ->filter(fn ($detail): bool => is_array($detail) && (int) ($detail['productDetailID'] ?? 0) > 0)
            ->values();
        $activeDetails = $details->filter(fn (array $detail): bool => (bool) ($detail['flagActive'] ?? true));

        if ($activeDetails->isEmpty()) {
            return [];
        }

        $baseId = (int) ($activeDetails->first(fn (array $detail): bool => (bool) ($detail['isBase'] ?? false)) ?? $activeDetails->first())['productDetailID'];

        return $details
            ->map(fn (array $detail): array => [
                'company_code' => $companyCode,
                'esb_product_id' => ((int) ($product['productID'] ?? 0)) ?: null,
                'product_detail_id' => (int) $detail['productDetailID'],
                'product_code' => filled($product['productCode'] ?? null) ? trim((string) $product['productCode']) : null,
                'product_name' => trim((string) ($product['productName'] ?? '')) ?: 'Produk '.$detail['productDetailID'],
                'uom_name' => ($detail['uomName'] ?? $detail['unit'] ?? null) ?: null,
                'is_base' => (int) $detail['productDetailID'] === $baseId,
                'category_name' => trim((string) ($product['categoryName'] ?? $product['categoryNameCategory'] ?? '')) ?: null,
                'is_active' => true,
                'last_synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ])
            ->unique('product_detail_id')
            ->values()
            ->all();
    }

    private function putProgress(array $progress): void
    {
        Cache::put(self::PROGRESS_CACHE_KEY, $progress, now()->addHours(2));
    }
}
