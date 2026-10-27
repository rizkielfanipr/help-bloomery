<?php

namespace App\Services\Ordering;

use App\Models\BranchEsbCode;
use App\Services\EsbService;
use App\Services\Monitoring\MonitoringSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Daily Sales Material Usage for one company pair and day — the primary demand source
 * (docs/products/analytics/ordering-recommendation/prd.md §9.2). Rows are kept per company pair: the existing
 * multi-pair helper merges companies by Product Code and is deliberately not used here.
 *
 * Contract (EsbService): one unpaginated response per branch/date with productCode, productName,
 * totalQty, and unit. A successful response lists every product used that day, so a product it
 * does not return was not used (0) — but a failed or invalid response is never read as 0.
 */
class OrderingDailyUsageSource
{
    public function __construct(private readonly EsbService $esb, private readonly MonitoringSanitizer $sanitizer) {}

    public function fetch(BranchEsbCode $pair, string $date, string $flagUnit): OrderingSourceResult
    {
        if (! $pair->is_active || blank($pair->esb_branch_code) || blank($pair->esb_comcode)) {
            return OrderingSourceResult::failed('Mapping Company Code dan ESB Branch Code belum lengkap.', false);
        }
        $token = $pair->esb_token;
        if (blank($token)) {
            return OrderingSourceResult::failed('Token ESB untuk mapping ini belum dikonfigurasi.', false);
        }

        try {
            $response = $this->esb->dailySalesMaterialUsageResponse($pair->esb_branch_code, $date, $flagUnit, $token);
        } catch (ConnectionException) {
            return OrderingSourceResult::failed('Koneksi ke ESB gagal atau timeout.', true);
        } catch (Throwable $exception) {
            return OrderingSourceResult::failed($this->safe('Daily Usage gagal: '.$exception->getMessage()), true);
        }

        if ($response->failed()) {
            $status = $response->status();

            return OrderingSourceResult::failed("ESB Daily Usage HTTP {$status}.", $status === 429 || $status >= 500);
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_is_list($payload)) {
            return OrderingSourceResult::failed('Format respons Daily Usage tidak valid.', false);
        }

        $rows = [];
        foreach ($payload as $row) {
            if (! is_array($row) || blank($row['productCode'] ?? null) || blank($row['unit'] ?? null) || ! is_numeric($row['totalQty'] ?? null)) {
                return OrderingSourceResult::failed('Baris Daily Usage tanpa Product Code, unit, atau quantity yang valid.', false);
            }
            if (filled($row['branchCode'] ?? null) && strcasecmp((string) $row['branchCode'], $pair->esb_branch_code) !== 0) {
                return OrderingSourceResult::failed('Daily Usage mengembalikan data cabang lain.', false);
            }
            if (filled($row['salesDate'] ?? null) && substr((string) $row['salesDate'], 0, 10) !== $date) {
                return OrderingSourceResult::failed('Daily Usage mengembalikan tanggal lain.', false);
            }

            $key = trim((string) $row['productCode']).'|'.mb_strtoupper(trim((string) $row['unit']));
            $rows[$key] ??= [
                'product_code' => trim((string) $row['productCode']),
                'product_name' => trim((string) ($row['productName'] ?? '')) ?: null,
                'uom' => mb_strtoupper(trim((string) $row['unit'])),
                'quantity' => 0.0,
            ];
            $rows[$key]['quantity'] += (float) $row['totalQty'];
        }

        return OrderingSourceResult::completed(array_values($rows));
    }

    private function safe(string $message): string
    {
        return (string) $this->sanitizer->message($message, 300);
    }
}
