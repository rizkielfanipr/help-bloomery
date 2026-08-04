<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EsbService
{
    private string $baseUrl;

    private string $token;

    /**
     * Return active ESB product details flattened for BOM selectors.
     *
     * @return array<int, array{productDetailID:int, productID:int, productCode:string, productName:string, categoryName:string, subCategoryName:string, unit:string, baseUnit:string, conversionFactor:float, sku:string, basePrice:float, receiptTolerance:float}>
     */
    public function getActiveProductDetails(): array
    {
        $baseUrl = rtrim((string) config('esb.master_product.base_url'), '/');
        $token = (string) config('esb.master_product.token');

        if ($baseUrl === '' || $token === '') {
            throw new \RuntimeException('Token static Master Product ESB belum dikonfigurasi.');
        }

        $products = [];
        $page = 1;

        do {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(60)
                ->get($baseUrl.'/corev1/master/product', [
                    'statusActive' => 'Yes',
                    'page' => $page,
                ]);

            $payload = $response->json();
            if ($response->failed() || ! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
                $message = is_array($payload) ? ($payload['message'] ?? null) : null;

                throw new \RuntimeException(
                    'Gagal mengambil Master Product ESB'.($message ? ': '.$message : ' (HTTP '.$response->status().').')
                );
            }

            $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
            foreach ($result['data'] ?? [] as $product) {
                foreach ($product['productDetails'] ?? [] as $detail) {
                    $detailId = (int) ($detail['productDetailID'] ?? 0);
                    if ($detailId < 1) {
                        continue;
                    }

                    $products[$detailId] = [
                        'productDetailID' => $detailId,
                        'productID' => (int) ($product['productID'] ?? 0),
                        'productCode' => (string) ($product['productCode'] ?? ''),
                        'productName' => (string) ($product['productName'] ?? ''),
                        'categoryName' => (string) ($product['categoryName'] ?? ''),
                        'subCategoryName' => (string) ($product['subCategoryName'] ?? ''),
                        'unit' => (string) ($detail['unit'] ?? ''),
                        'baseUnit' => $this->resolveBaseUnit($product),
                        'conversionFactor' => (float) ($detail['conversionFactor'] ?? 0),
                        'sku' => (string) ($detail['sku'] ?? ''),
                        'basePrice' => (float) ($detail['basePrice'] ?? 0),
                        'receiptTolerance' => (float) ($product['receiptTolerance'] ?? 0),
                    ];
                }
            }

            $currentPage = (int) ($result['page'] ?? $page);
            $limit = max(1, (int) ($result['limit'] ?? count($result['data'] ?? [])));
            $count = (int) ($result['count'] ?? count($result['data'] ?? []));
            $page++;
            $hasNextPage = (
                filled($result['next'] ?? null)
                || ($count > $currentPage * $limit)
            ) && $page <= 500;
        } while ($hasNextPage);

        uasort($products, fn (array $left, array $right) => [
            $left['productName'],
            $left['unit'],
        ] <=> [
            $right['productName'],
            $right['unit'],
        ]);

        return $products;
    }

    /**
     * @return array{data:array<int, array>, page:int, total:int, perPage:int, hasNext:bool}
     */
    public function getActiveProductDetailsPage(
        string $productName = '',
        int $page = 1,
        string $productCode = '',
    ): array {
        $baseUrl = rtrim((string) config('esb.master_product.base_url'), '/');
        $token = (string) config('esb.master_product.token');

        if ($baseUrl === '' || $token === '') {
            throw new \RuntimeException('Token static Master Product ESB belum dikonfigurasi.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get($baseUrl.'/corev1/master/product', array_filter([
                'statusActive' => 'Yes',
                'productName' => trim($productName),
                'productCode' => trim($productCode),
                'page' => max(1, $page),
            ], fn ($value) => $value !== ''));

        $payload = $response->json();
        if ($response->failed() || ! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
            $message = is_array($payload) ? ($payload['message'] ?? null) : null;

            if (is_string($message) && str_contains(strtolower($message), 'not found')) {
                return [
                    'data' => [],
                    'page' => max(1, $page),
                    'total' => 0,
                    'perPage' => 10,
                    'hasNext' => false,
                ];
            }

            throw new \RuntimeException(
                'Gagal mengambil Master Product ESB'.($message ? ': '.$message : ' (HTTP '.$response->status().').')
            );
        }

        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $products = [];

        foreach ($result['data'] ?? [] as $product) {
            foreach ($product['productDetails'] ?? [] as $detail) {
                $detailId = (int) ($detail['productDetailID'] ?? 0);
                if ($detailId < 1) {
                    continue;
                }

                $products[$detailId] = $this->mapProductDetail($product, $detail);
            }
        }

        uasort($products, fn (array $left, array $right) => [
            $left['productName'],
            $left['unit'],
        ] <=> [
            $right['productName'],
            $right['unit'],
        ]);

        $currentPage = (int) ($result['page'] ?? $page);
        $total = (int) ($result['count'] ?? count($products));
        $limit = max(1, (int) ($result['limit'] ?? count($result['data'] ?? [])));

        return [
            'data' => $products,
            'page' => $currentPage,
            'total' => $total,
            'perPage' => $limit,
            'hasNext' => filled($result['next'] ?? null) || ($currentPage * $limit < $total),
        ];
    }

    /**
     * Resolve one active Master Product detail using an exact Product Detail ID.
     */
    public function findActiveProductDetail(
        int $productDetailId,
        string $productCode = '',
        string $productName = '',
    ): ?array {
        if ($productDetailId < 1) {
            return null;
        }

        foreach ([
            ['name' => '', 'code' => $productCode],
            ['name' => $productName, 'code' => ''],
        ] as $search) {
            if (trim($search['name']) === '' && trim($search['code']) === '') {
                continue;
            }

            $result = $this->getActiveProductDetailsPage(
                $search['name'],
                1,
                $search['code'],
            );

            if (isset($result['data'][$productDetailId])) {
                return $result['data'][$productDetailId];
            }
        }

        return null;
    }

    private function mapProductDetail(array $product, array $detail): array
    {
        return [
            'productDetailID' => (int) $detail['productDetailID'],
            'productID' => (int) ($product['productID'] ?? 0),
            'productCode' => (string) ($product['productCode'] ?? ''),
            'productName' => (string) ($product['productName'] ?? ''),
            'categoryName' => (string) ($product['categoryName'] ?? ''),
            'subCategoryName' => (string) ($product['subCategoryName'] ?? ''),
            'unit' => (string) ($detail['unit'] ?? ''),
            'baseUnit' => $this->resolveBaseUnit($product),
            'conversionFactor' => (float) ($detail['conversionFactor'] ?? 0),
            'sku' => (string) ($detail['sku'] ?? ''),
            'basePrice' => (float) ($detail['basePrice'] ?? 0),
            'receiptTolerance' => (float) ($product['receiptTolerance'] ?? 0),
        ];
    }

    private function resolveBaseUnit(array $product): string
    {
        foreach ($product['productDetails'] ?? [] as $detail) {
            $baseUnitFlag = strtolower((string) data_get($detail, 'defaultUnit.baseUnit', ''));
            if (in_array($baseUnitFlag, ['yes', '1', 'true'], true)) {
                return (string) ($detail['unit'] ?? '');
            }
        }

        return (string) data_get($product, 'productDetails.0.unit', '');
    }

    /**
     * Format a BOM result product for display, e.g. "WIP | Adonan Bolu GR - Resep (200 GR)".
     */
    public static function formatResultLabel(string $productName, string $uomName, ?string $baseUnit, ?float $conversionFactor): string
    {
        $label = $productName;

        if (filled($baseUnit) && $baseUnit !== $uomName) {
            $label .= ' '.$baseUnit;
        }

        $label .= ' - '.$uomName;

        if (filled($baseUnit) && $conversionFactor > 0) {
            $factor = rtrim(rtrim(number_format($conversionFactor, 4, '.', ''), '0'), '.');
            $label .= " ($factor $baseUnit)";
        }

        return $label;
    }

    /**
     * Fetch product details for exact product codes in parallel.
     *
     * @return array<int, array>
     */
    public function getActiveProductDetailsByCodes(array $productCodes): array
    {
        $baseUrl = rtrim((string) config('esb.master_product.base_url'), '/');
        $token = (string) config('esb.master_product.token');
        $codes = array_values(array_unique(array_filter(array_map('strval', $productCodes))));

        if ($baseUrl === '' || $token === '') {
            throw new \RuntimeException('Token static Master Product ESB belum dikonfigurasi.');
        }

        if ($codes === []) {
            return [];
        }

        $responses = Http::pool(fn (Pool $pool): array => array_map(
            fn (string $code) => $pool
                ->as($code)
                ->withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->get($baseUrl.'/corev1/master/product', [
                    'statusActive' => 'Yes',
                    'productCode' => $code,
                    'page' => 1,
                ]),
            $codes,
        ));

        $products = [];
        foreach ($responses as $response) {
            if (! $response || $response->failed()) {
                continue;
            }

            $payload = $response->json();
            if (! is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
                continue;
            }

            foreach (data_get($payload, 'result.data', []) as $product) {
                foreach ($product['productDetails'] ?? [] as $detail) {
                    $detailId = (int) ($detail['productDetailID'] ?? 0);
                    if ($detailId > 0) {
                        $products[$detailId] = $this->mapProductDetail($product, $detail);
                    }
                }
            }
        }

        return $products;
    }

    /**
     * Return every unit used by active Master Product details.
     *
     * @return array<int, string>
     */
    public function getAllActiveProductUnits(): array
    {
        return Cache::remember('esb.master_product_units', now()->addDay(), function (): array {
            $baseUrl = rtrim((string) config('esb.master_product.base_url'), '/');
            $token = (string) config('esb.master_product.token');

            $first = Http::withToken($token)->acceptJson()->timeout(20)
                ->get($baseUrl.'/corev1/master/product', ['statusActive' => 'Yes', 'page' => 1]);

            if ($first->failed() || data_get($first->json(), 'status') !== 'ok') {
                throw new \RuntimeException('Gagal mengambil daftar Unit Master Product ESB.');
            }

            $firstResult = data_get($first->json(), 'result', []);
            $rows = is_array($firstResult['data'] ?? null) ? $firstResult['data'] : [];
            $limit = max(1, (int) ($firstResult['limit'] ?? count($rows)));
            $lastPage = max(1, (int) ceil(((int) ($firstResult['count'] ?? count($rows))) / $limit));
            $remainingPages = $lastPage > 1 ? range(2, $lastPage) : [];

            foreach (array_chunk($remainingPages, 30) as $pages) {
                $responses = Http::pool(fn (Pool $pool): array => array_map(
                    fn (int $page) => $pool->as((string) $page)
                        ->withToken($token)
                        ->acceptJson()
                        ->timeout(20)
                        ->get($baseUrl.'/corev1/master/product', [
                            'statusActive' => 'Yes',
                            'page' => $page,
                        ]),
                    $pages,
                ));

                foreach ($responses as $response) {
                    if ($response && $response->successful()) {
                        $pageRows = data_get($response->json(), 'result.data', []);
                        if (is_array($pageRows)) {
                            array_push($rows, ...$pageRows);
                        }
                    }
                }
            }

            $units = collect($rows)
                ->flatMap(fn (array $product) => collect($product['productDetails'] ?? [])->pluck('unit'))
                ->filter()
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return $units;
        });
    }

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('esb.base_url'), '/');
        $this->token = (string) config('esb.token');
    }

    /**
     * Fetch all sales for a branch on a given date and return payment totals grouped by method.
     *
     * @return array<int, array{name: string, type: string, total: float}>
     *
     * @throws \RuntimeException
     */
    public function getPaymentSummary(string $branchCode, string $date, ?string $token = null): array
    {
        $resolvedToken = $token ?? $this->token;

        $totals = [];
        $page = 1;
        $pageCount = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$resolvedToken,
                'Content-Type' => 'application/json',
            ])->timeout(60)->get($this->baseUrl.'/corev1/sales/sales-information', [
                'salesDateFrom' => $date,
                'salesDateTo' => $date,
                'branchCode' => $branchCode,
                'statusName' => 'Finished',
                'page' => $page,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('ESB API error: '.$response->status().' '.$response->body());
            }

            $pageCount = (int) ($response->header('X-Pagination-Page-Count') ?: 1);

            foreach ($response->json() ?? [] as $sale) {
                foreach ($sale['salesPayments'] ?? [] as $payment) {
                    $name = $payment['paymentMethodName'] ?? 'Unknown';
                    $type = $payment['paymentMethodTypeName'] ?? '';
                    $amount = $this->netPaymentAmount($sale, $payment);

                    if (! isset($totals[$name])) {
                        $totals[$name] = ['name' => $name, 'type' => $type, 'total' => 0.0];
                    }
                    $totals[$name]['total'] += $amount;
                }
            }

            $page++;
        } while ($page <= $pageCount);

        $rows = array_values($totals);

        usort($rows, fn ($a, $b) => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);

        return $rows;
    }

    /**
     * CASH payments in ESB record the cash physically tendered by the customer,
     * which can exceed the bill when change is given back (e.g. paying a Rp35.000
     * bill with a Rp50.000 note records paymentAmount=50.000, not the Rp35.000
     * actually kept). For a sale paid with a single CASH payment, cap it at the
     * sale's grandTotal so reconciliation compares against net sales, not the
     * gross cash handed over. Split-payment and non-cash amounts are untouched.
     */
    public function netPaymentAmount(array $sale, array $payment): float
    {
        $amount = (float) ($payment['paymentAmount'] ?? 0);
        $method = mb_strtoupper(trim((string) ($payment['paymentMethodName'] ?? '')));

        if ($method !== 'CASH' || count($sale['salesPayments'] ?? []) !== 1) {
            return $amount;
        }

        return min($amount, (float) ($sale['grandTotal'] ?? $amount));
    }

    /**
     * Fetch and aggregate finished ESB transactions whose salesDateOut is within
     * the half-open shift interval [start, end).
     *
     * @return array{rows: array<int, array{name: string, type: string, total: float}>, transactions: array<int, array{sales_num: string, sales_date_out: string, payment_total: float}>}
     */
    public function getShiftPaymentSummary(
        string $branchCode,
        string $reportDate,
        string $startTime,
        string $endTime,
        ?string $token = null,
    ): array {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $startedAt = CarbonImmutable::parse($reportDate.' '.$startTime, $timezone);
        $endedAt = CarbonImmutable::parse($reportDate.' '.$endTime, $timezone);

        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            $endedAt = $endedAt->addDay();
        }

        $sales = $this->getRawSales(
            $branchCode,
            $startedAt->toDateString(),
            $endedAt->toDateString(),
            $token ?? $this->token,
        );

        $totals = [];
        $transactions = [];

        foreach ($sales as $sale) {
            $dateOut = $sale['salesDateOut'] ?? null;
            if (! $dateOut) {
                continue;
            }

            $completedAt = CarbonImmutable::parse($dateOut, $timezone);
            if ($completedAt->lessThan($startedAt) || ! $completedAt->lessThan($endedAt)) {
                continue;
            }

            $paymentTotal = 0.0;
            foreach ($sale['salesPayments'] ?? [] as $payment) {
                $name = $payment['paymentMethodName'] ?? 'Unknown';
                $type = $payment['paymentMethodTypeName'] ?? '';
                $amount = $this->netPaymentAmount($sale, $payment);
                $key = $type.'|'.$name;

                $totals[$key] ??= ['name' => $name, 'type' => $type, 'total' => 0.0];
                $totals[$key]['total'] += $amount;
                $paymentTotal += $amount;
            }

            $salesNum = (string) ($sale['salesNum'] ?? '');
            if ($salesNum !== '') {
                $transactions[$salesNum] = [
                    'sales_num' => $salesNum,
                    'sales_date_out' => $completedAt->format('Y-m-d H:i:s'),
                    'payment_total' => $paymentTotal,
                ];
            }
        }

        $rows = array_values($totals);
        usort($rows, fn ($a, $b) => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);

        return ['rows' => $rows, 'transactions' => array_values($transactions)];
    }

    /**
     * Fetch all finished sales transactions for a branch over a date range.
     *
     * @return array<int, mixed>
     *
     * @throws \RuntimeException
     */
    public function getRawSales(string $branchCode, string $dateFrom, string $dateTo, string $token): array
    {
        $all = [];
        $page = 1;
        $pageCount = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->timeout(60)->get($this->baseUrl.'/corev1/sales/sales-information', [
                'salesDateFrom' => $dateFrom,
                'salesDateTo' => $dateTo,
                'branchCode' => $branchCode,
                'statusName' => 'Finished',
                'page' => $page,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('ESB API error: '.$response->status().' '.$response->body());
            }

            $pageCount = (int) ($response->header('X-Pagination-Page-Count') ?: 1);
            array_push($all, ...($response->json() ?? []));
            $page++;
        } while ($page <= $pageCount);

        return $all;
    }

    /**
     * Fetch a single page of finished sales.
     *
     * @return array{data: array<int, mixed>, pageCount: int}
     *
     * @throws \RuntimeException
     */
    public function getSalesPage(string $branchCode, string $dateFrom, string $dateTo, string $token, int $page): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ])
            ->timeout(60)
            ->retry(
                times: 3,
                sleepMilliseconds: fn (int $attempt): int => $attempt * 500,
                when: fn (\Throwable $exception): bool => $exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429),
                throw: false,
            )
            ->get($this->baseUrl.'/corev1/sales/sales-information', [
                'salesDateFrom' => $dateFrom,
                'salesDateTo' => $dateTo,
                'branchCode' => $branchCode,
                'statusName' => 'Finished',
                'page' => $page,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('ESB API error: '.$response->status().' '.$response->body());
        }

        return [
            'data' => $response->json() ?? [],
            'pageCount' => (int) ($response->header('X-Pagination-Page-Count') ?: 1),
        ];
    }

    /**
     * Stream finished sales page-by-page, calling $eachPage with each page's rows.
     * Memory-efficient alternative to getRawSales for large date ranges.
     *
     * @param  callable(array<int, mixed>): void  $eachPage
     *
     * @throws \RuntimeException
     */
    public function streamRawSales(string $branchCode, string $dateFrom, string $dateTo, string $token, callable $eachPage): void
    {
        $page = 1;
        $pageCount = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->timeout(60)->get($this->baseUrl.'/corev1/sales/sales-information', [
                'salesDateFrom' => $dateFrom,
                'salesDateTo' => $dateTo,
                'branchCode' => $branchCode,
                'statusName' => 'Finished',
                'page' => $page,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('ESB API error: '.$response->status().' '.$response->body());
            }

            $pageCount = (int) ($response->header('X-Pagination-Page-Count') ?: 1);
            $eachPage($response->json() ?? []);
            $page++;
        } while ($page <= $pageCount);
    }

    /**
     * Fetch promotion list (static catalog) for a branch or all branches.
     *
     * @return array<int, mixed>
     *
     * @throws \RuntimeException
     */
    public function getPromotionList(string $branchCode = '', ?string $token = null): array
    {
        $resolvedToken = $token ?? $this->token;

        $params = [];
        if ($branchCode !== '') {
            $params['branchCode'] = $branchCode;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$resolvedToken,
            'Content-Type' => 'application/json',
        ])->timeout(30)->get($this->baseUrl.'/extv1/promotion', $params);

        if ($response->failed()) {
            throw new \RuntimeException('ESB Promo API error: '.$response->status().' '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Fetch daily sales material usage for a branch on a given date.
     *
     * @return array<int, array{branchCode: string, branch: string, salesDate: string, productCode: string, productName: string, totalQty: string, unit: string, totalConversionQty: string, unitConversion: string}>
     *
     * @throws \RuntimeException
     */
    public function getDailySalesMaterialUsage(string $branchCode, string $salesDate, string $flagUnit, string $token): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ])->timeout(60)->get($this->baseUrl.'/corev1/sales/get-daily-sales-material-usage', [
            'branchCode' => $branchCode,
            'salesDate' => $salesDate,
            'flagUnit' => $flagUnit,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('ESB Material Usage API error: '.$response->status().' '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }
}
