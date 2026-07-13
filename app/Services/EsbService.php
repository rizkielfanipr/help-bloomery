<?php

namespace App\Services;

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
            ])->get($this->baseUrl.'/corev1/sales/sales-information', [
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

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }
}
