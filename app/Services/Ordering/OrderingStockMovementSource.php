<?php

namespace App\Services\Ordering;

use App\Models\BranchEsbCode;
use App\Services\EsbStockMovementService;
use App\Services\Monitoring\MonitoringSanitizer;
use Throwable;

/**
 * Stock Movement for one company pair and day through the existing EsbStockMovementService
 * (pagination, branch guard, company client), normalized for the daily metric builder
 * (docs/ordering-recommendation-prd.md §9.3). Categories come from the same company's Master
 * Product list; a category failure never blocks the stock read.
 */
class OrderingStockMovementSource
{
    public function __construct(private readonly EsbStockMovementService $stock, private readonly MonitoringSanitizer $sanitizer) {}

    public function fetch(BranchEsbCode $pair, string $date, string $flagUnit): OrderingSourceResult
    {
        try {
            $rows = $this->stock->movements($pair, $date, $date, $flagUnit);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $permanent = str_contains($message, 'di luar cabang') || str_contains($message, 'belum lengkap') || str_contains($message, 'tidak valid');

            return OrderingSourceResult::failed($this->sanitizer->message('Stock Movement gagal: '.$message, 300) ?? 'Stock Movement gagal.', ! $permanent);
        }

        try {
            $categories = $this->stock->categories($pair->esb_comcode);
        } catch (Throwable) {
            $categories = [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['productCode'] ?? ''));
            $uom = mb_strtoupper(trim((string) ($row['UOM'] ?? '')));
            if ($code === '' || $uom === '') {
                continue;
            }

            $normalized[] = [
                'product_code' => $code,
                'product_name' => trim((string) ($row['productName'] ?? '')) ?: null,
                'category_name' => filled($categories[$code] ?? null) ? trim((string) $categories[$code]) : null,
                'uom' => $uom,
                'transaction_type' => trim((string) ($row['transactionType'] ?? '')),
                'document_code' => trim((string) ($row['documentCode'] ?? '')),
                'qty_in' => (float) ($row['qtyIn'] ?? 0),
                'qty_out' => (float) ($row['qtyOut'] ?? 0),
                'qty_balance' => is_numeric($row['qtyBalance'] ?? null) ? (float) $row['qtyBalance'] : null,
                'location_key' => (string) ($row['locationID'] ?? $row['locationId'] ?? $row['location'] ?? $row['locationName'] ?? ''),
                'location_name' => trim((string) ($row['location'] ?? $row['locationName'] ?? '')),
                'document_date' => (string) ($row['documentDate'] ?? ''),
                'created_date' => (string) ($row['createdDate'] ?? ''),
            ];
        }

        return OrderingSourceResult::completed($normalized, max(1, (int) ceil(count($rows) / 100)));
    }
}
