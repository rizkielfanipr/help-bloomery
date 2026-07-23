<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class EsbService
{
    private string $baseUrl;

    private string $token;

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
                    $amount = (float) ($payment['paymentAmount'] ?? 0);

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
                $amount = (float) ($payment['paymentAmount'] ?? 0);
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
