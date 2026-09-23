<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchEsbCode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EsbStockMovementService extends EsbItemJournalService
{
    /** @param list<string> $observedTypes
     * @return list<string>
     */
    public function transactionTypes(array $observedTypes = []): array
    {
        $types = collect(config('esb.stock_movement.transaction_types', []))
            ->merge($observedTypes)
            ->map(fn (string $type): string => trim($type))
            ->filter()->unique()->values()->all();
        natcasesort($types);

        return array_values($types);
    }

    public function unitToShow(string $unit): string
    {
        return match ($unit) {
            'stockUnit', 'Default Stock Unit' => 'Default Stock Unit',
            'salesUnit', 'Default Stock Sales' => 'Default Stock Sales',
            'purchaseUnit', 'Default Purchase Unit' => 'Default Purchase Unit',
            'baseUnit', 'Base Unit' => 'Base Unit',
            'transferUnit', 'Default Transfer Unit' => 'Default Transfer Unit',
            default => throw new RuntimeException('Satuan Stock Movement tidak valid.'),
        };
    }

    /** @return array<string, mixed> */
    public function movementPage(BranchEsbCode $pair, string $from, string $to, string $unit = 'stockUnit', int $page = 1): array
    {
        if (! $pair->is_active || blank($pair->esb_comcode) || blank($pair->esb_branch_code)) {
            throw new RuntimeException('Mapping Company Code dan ESB Branch Code belum lengkap.');
        }
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        if ($start->gt($end) || $end->gt(CarbonImmutable::today())) {
            throw new RuntimeException('Periode Stock Movement tidak valid.');
        }

        $result = $this->requestResult($pair->esb_comcode, 'get', '/report/stock-movement', [
            'startPeriod' => $start->toDateString(),
            'endPeriod' => $end->toDateString(),
            'branchCode' => $pair->esb_branch_code,
            'unitToShow' => $this->unitToShow($unit),
            'page' => max(1, $page),
            'limit' => 100,
        ], 'mengambil Stock Movement '.$pair->esb_branch_code);
        if (! is_array($result['data'] ?? null)) {
            throw new RuntimeException('Format respons Stock Movement tidak valid.');
        }
        foreach ($result['data'] as $row) {
            if (strcasecmp((string) ($row['branchCode'] ?? ''), $pair->esb_branch_code) !== 0) {
                throw new RuntimeException('Stock Movement mengembalikan data di luar cabang yang dipilih.');
            }
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function movements(BranchEsbCode $pair, string $from, string $to, string $unit = 'stockUnit'): array
    {
        $rows = [];
        for ($page = 1; $page <= 1000; $page++) {
            $result = $this->movementPage($pair, $from, $to, $unit, $page);
            array_push($rows, ...$result['data']);
            $hasNext = filled($result['next'] ?? null)
                || $page * 100 < (int) ($result['count'] ?? 0);
            if (! $hasNext) {
                return $rows;
            }
            if ($result['data'] === []) {
                throw new RuntimeException('Pagination Stock Movement tidak lengkap.');
            }
        }

        throw new RuntimeException('Stock Movement melebihi batas pengambilan halaman.');
    }

    /** @return array<string, string> */
    public function categories(string $company): array
    {
        return Cache::remember('stock-movement.categories.'.$company, now()->addHours(6), function () use ($company): array {
            $categories = [];
            for ($page = 1; $page <= 1000; $page++) {
                $result = $this->products($company, ['page' => $page, 'limit' => 100]);
                foreach ($result['data'] as $product) {
                    $categories[(string) ($product['productCode'] ?? '')] = (string) ($product['categoryName'] ?? '');
                }
                if (! filled($result['next']) && $page * 100 >= $result['count']) {
                    return $categories;
                }
                if ($result['data'] === []) {
                    throw new RuntimeException('Pagination Master Product tidak lengkap.');
                }
            }

            throw new RuntimeException('Master Product melebihi batas pengambilan halaman.');
        });
    }

    /** @return array<string, mixed> */
    public function getRollingStockCardProductsForBranch(Branch $branch, CarbonInterface|string $reportDate, string $flagUnit = 'stockUnit'): array
    {
        $to = CarbonImmutable::parse($reportDate)->toDateString();
        $from = CarbonImmutable::parse($reportDate)->toDateString();

        return Cache::remember($this->stockCardCatalogCacheKey($branch, $reportDate, $flagUnit), now()->addMinutes(5), function () use ($branch, $from, $to, $flagUnit): array {
            $pair = $this->stockCardSource($branch);
            $products = [];
            $categories = $this->categories($pair->esb_comcode);
            $products = $this->mergeCatalogRows($products, $this->movements($pair, $from, $to, $flagUnit), $categories, $pair->esb_comcode);

            return $this->buildStockCardProductCatalog($products, $from, $to);
        });
    }

    /** @return array<string, array<string, mixed>> */
    public function mergeCatalogRows(array $products, array $rows, array $categories, ?string $company = null): array
    {
        foreach ($rows as $row) {
            $code = trim((string) ($row['productCode'] ?? ''));
            $name = trim((string) ($row['productName'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }
            $unit = (string) ($row['UOM'] ?? '');
            if (isset($products[$code]) && $products[$code]['unit'] !== $unit) {
                throw new RuntimeException("Satuan produk {$code} berbeda antar mapping ESB; qty tidak dapat digabungkan.");
            }
            $products[$code] ??= [
                'product_code' => $code, 'product_name' => $name, 'category' => $categories[$code] ?? '',
                'unit' => $unit, 'usage_dates' => [], 'total_qty' => 0.0,
            ];
            if ($company !== null) {
                $products[$code]['category_sources'][$company] = trim($categories[$code] ?? '');
            }
            $products[$code]['usage_dates'][(string) $row['documentDate']] = true;
            $products[$code]['total_qty'] += (float) ($row['qtyOut'] ?? 0);
        }

        return $products;
    }

    /** @return array{rows:list<array<string, mixed>>,ok:bool,types:list<string>,transactions:array<string, array<string, array{qty_in:float,qty_out:float}>>,units:array<string,string>} */
    public function balancesForBranch(Branch $branch, string $date, string $unit): array
    {
        $pair = $this->stockCardSource($branch);
        $totals = [];
        $types = [];
        $transactions = [];
        $units = [];
        $rows = $this->movements($pair, CarbonImmutable::parse($date)->toDateString(), $date, $unit);
        $latest = [];
        foreach ($rows as $row) {
            $type = trim((string) ($row['transactionType'] ?? '')) ?: 'Tanpa Tipe';
            $types[$type] = true;
            $code = trim((string) ($row['productCode'] ?? ''));
            if ($code !== '') {
                $rowUnit = (string) ($row['UOM'] ?? '');
                if (isset($units[$code]) && $units[$code] !== $rowUnit) {
                    throw new RuntimeException("Satuan saldo produk {$code} berbeda; qty tidak dapat digabungkan.");
                }
                $units[$code] = $rowUnit;
                $transactions[$code][$type] ??= ['qty_in' => 0.0, 'qty_out' => 0.0];
                $transactions[$code][$type]['qty_in'] += (float) ($row['qtyIn'] ?? 0);
                $transactions[$code][$type]['qty_out'] += (float) ($row['qtyOut'] ?? 0);
            }
            if ($code === '' || ! is_numeric($row['qtyBalance'] ?? null)) {
                continue;
            }
            $key = $code.'|'.($row['location'] ?? '').'|'.($row['UOM'] ?? '');
            $order = [(string) ($row['documentDate'] ?? ''), (string) ($row['createdDate'] ?? '')];
            if (! isset($latest[$key]) || $order >= $latest[$key]['order']) {
                $latest[$key] = ['row' => $row, 'order' => $order];
            }
        }
        foreach ($latest as $balance) {
            $row = $balance['row'];
            $code = (string) $row['productCode'];
            if (isset($totals[$code]) && $totals[$code]['unit'] !== $row['UOM']) {
                throw new RuntimeException("Satuan saldo produk {$code} berbeda; qty tidak dapat digabungkan.");
            }
            $totals[$code] ??= ['productCode' => $code, 'productName' => (string) ($row['productName'] ?? $code), 'unit' => $row['UOM'], 'totalQty' => 0.0];
            $totals[$code]['companies'] = array_values(array_unique([...($totals[$code]['companies'] ?? []), $pair->esb_comcode]));
            $totals[$code]['totalQty'] += (float) $row['qtyBalance'];
        }

        $transactionTypes = $this->transactionTypes(array_keys($types));

        return ['rows' => array_values($totals), 'ok' => true, 'types' => array_values($transactionTypes),
            'transactions' => $transactions, 'units' => $units];
    }

    public function stockCardCatalogCacheKey(Branch $branch, CarbonInterface|string $reportDate, string $flagUnit = 'stockUnit'): string
    {
        $pair = $branch->activeStockCardEsbCode();
        $mapping = $pair ? $pair->esb_comcode.'|'.$pair->esb_branch_code : 'unconfigured';

        return 'stock-movement.catalog.v4:'.$branch->id.':'.CarbonImmutable::parse($reportDate)->toDateString().':'.$flagUnit.':'.sha1($mapping);
    }

    private function stockCardSource(Branch $branch): BranchEsbCode
    {
        $pair = $branch->activeStockCardEsbCode();
        if (! $pair) {
            throw new RuntimeException('Sumber Stock Card belum diatur untuk Branch ini. Pilih satu mapping ESB aktif pada Master Branch.');
        }

        if (blank($pair->esb_comcode) || blank($pair->esb_branch_code)) {
            throw new RuntimeException('Mapping Company Code dan ESB Branch Code untuk sumber Stock Card belum lengkap.');
        }

        return $pair;
    }

    public function getCachedStockCardCatalog(Branch $branch, CarbonInterface|string $reportDate, string $flagUnit = 'stockUnit'): ?array
    {
        return Cache::get($this->stockCardCatalogCacheKey($branch, $reportDate, $flagUnit));
    }

    public function cacheStockCardCatalog(Branch $branch, CarbonInterface|string $reportDate, string $flagUnit, array $catalog): void
    {
        Cache::put($this->stockCardCatalogCacheKey($branch, $reportDate, $flagUnit), $catalog, now()->addMinutes(5));
    }

    /**
     * @param  array<string, array{product_code: string, product_name: string, category: string, unit: string, usage_dates: array<string, bool>, total_qty: float}>  $products
     * @return array{products: list<array{product_code: string, product_name: string, category: string, unit: string, usage_days: int, total_qty: float}>, period_from: string, period_to: string, failed_requests: int}
     */
    public function buildStockCardProductCatalog(
        array $products,
        string $periodFrom,
        string $periodTo,
        int $failedRequests = 0,
    ): array {
        $catalog = collect($products)->map(fn (array $product): array => [
            'product_code' => $product['product_code'],
            'product_name' => $product['product_name'],
            'category' => $product['category'] !== '' ? $product['category'] : 'Tanpa Kategori',
            'category_sources' => $product['category_sources'] ?? [],
            'unit' => $product['unit'],
            'usage_days' => count($product['usage_dates']),
            'total_qty' => $product['total_qty'],
        ]);

        return [
            'products' => $catalog->sortBy('product_name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'failed_requests' => $failedRequests,
        ];
    }
}
