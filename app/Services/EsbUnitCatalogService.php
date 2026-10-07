<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EsbUnitCatalogService
{
    public function __construct(private readonly EsbCoreClient $client) {}

    /** @return array<int, string> */
    public function options(string $companyCode = 'BLSS'): array
    {
        $companyCode = mb_strtoupper(trim($companyCode));

        try {
            return Cache::remember(
                $this->cacheKey($companyCode),
                now()->addDay(),
                fn (): array => $this->fetchOptions($companyCode),
            );
        } catch (RuntimeException) {
            return $this->fallbackOptions();
        }
    }

    /** @return array<int, string> */
    private function fetchOptions(string $companyCode): array
    {
        $options = [];
        $page = 1;

        do {
            $response = $this->client->request($companyCode, 'get', '/units', [
                'page' => $page,
                'limit' => 1000,
            ]);
            $result = $this->client->successfulResult(
                $response,
                'mengambil master unit',
                $companyCode,
                '/units',
            );

            foreach ((array) ($result['data'] ?? []) as $unit) {
                $unitId = (int) ($unit['uomID'] ?? 0);
                $unitName = trim((string) ($unit['uomName'] ?? ''));

                if ($unitId > 0 && $unitName !== '') {
                    $options[$unitId] = $unitName;
                }
            }

            $count = (int) ($result['count'] ?? count($options));
            $limit = max(1, (int) ($result['limit'] ?? 1000));
            $hasNextPage = filled($result['next'] ?? null) || ($page * $limit) < $count;
            $page++;
        } while ($hasNextPage && $page <= 100);

        $options = array_replace($this->fallbackOptions(), $options);
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** @return array<int, string> */
    private function fallbackOptions(): array
    {
        return collect((array) config('esb.core.uoms', []))
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    private function cacheKey(string $companyCode): string
    {
        return 'esb_core.unit_catalog.v1.'.$companyCode;
    }
}
