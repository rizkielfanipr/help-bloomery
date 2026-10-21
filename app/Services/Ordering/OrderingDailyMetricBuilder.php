<?php

namespace App\Services\Ordering;

use App\Models\BranchEsbCode;
use App\Models\OrderingDailyMetric;
use App\Models\OrderingSyncRun;
use Carbon\CarbonImmutable;

/**
 * Writes normalized source rows into the daily facts (docs/ordering-recommendation-prd.md §11.2,
 * §12). Upserts on the natural identity (branch, company pair, date, product code, UOM) so the
 * same input always yields the same rows. Only a complete read is applied: products a complete
 * read no longer returns are reset to 0 for that day, never left with an older value.
 *
 * Waste stays separate from sales-driven usage; POS Sales is stored for validation only and is
 * never added to the Daily Usage demand.
 */
class OrderingDailyMetricBuilder
{
    /** @param list<array{product_code: string, product_name: ?string, uom: string, quantity: float}> $rows */
    public function applyUsage(OrderingSyncRun $run, BranchEsbCode $pair, string $date, array $rows): int
    {
        $values = collect($rows)->map(fn (array $row): array => [
            ...$this->identity($pair, $date, $row['product_code'], $row['uom']),
            'product_name' => $row['product_name'],
            'sales_driven_usage' => round($row['quantity'], 4),
            'usage_sync_run_id' => $run->id,
            'input_version' => (string) config('ordering_recommendation.algorithm_version'),
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        $this->upsert($values, ['product_name', 'sales_driven_usage', 'usage_sync_run_id', 'input_version', 'updated_at']);
        $this->existing($pair, $date)
            ->whereNotIn('id', $this->idsFor($pair, $date, $values))
            ->update(['sales_driven_usage' => 0, 'usage_sync_run_id' => $run->id, 'updated_at' => now()]);

        return count($values);
    }

    /** @param list<array<string, mixed>> $rows */
    public function applyStock(OrderingSyncRun $run, BranchEsbCode $pair, string $date, array $rows): int
    {
        $config = config('ordering_recommendation');
        $scope = $config['stock_location_scope'];
        $wasteTypes = $this->lower($config['waste_transaction_types']);
        $posTypes = $this->lower($config['pos_sales_transaction_types']);
        $goodsInTypes = $this->lower($config['goods_in_transaction_types']);

        $products = [];
        foreach ($rows as $row) {
            $key = $row['product_code'].'|'.$row['uom'];
            $products[$key] ??= [
                'product_code' => $row['product_code'], 'product_name' => $row['product_name'], 'category_name' => $row['category_name'],
                'uom' => $row['uom'], 'pos' => 0.0, 'waste' => 0.0, 'goods_in' => 0.0, 'latest' => [],
            ];
            $type = mb_strtolower($row['transaction_type']);
            if (in_array($type, $posTypes, true)) {
                $products[$key]['pos'] += $row['qty_out'];
            }
            if (in_array($type, $wasteTypes, true)) {
                $products[$key]['waste'] += $row['qty_out'];
            }
            if (in_array($type, $goodsInTypes, true)) {
                $products[$key]['goods_in'] += $row['qty_in'];
            }

            // Latest balance per location: balances are never summed over time.
            if ($row['qty_balance'] !== null && $this->inScope($row['location_name'], $row['location_key'], $scope)) {
                $order = [$row['document_date'], $row['created_date']];
                $current = $products[$key]['latest'][$row['location_key']] ?? null;
                if ($current === null || $order >= $current['order']) {
                    $products[$key]['latest'][$row['location_key']] = ['order' => $order, 'balance' => $row['qty_balance'], 'observed_at' => $row['created_date'] ?: $row['document_date']];
                }
            }
        }

        $values = collect($products)->map(fn (array $product): array => [
            ...$this->identity($pair, $date, $product['product_code'], $product['uom']),
            'product_name' => $product['product_name'],
            'category_name' => $product['category_name'],
            'pos_sales_usage' => $posTypes === [] ? null : round($product['pos'], 4),
            'waste_quantity' => $wasteTypes === [] ? null : round($product['waste'], 4),
            'goods_in_quantity' => $goodsInTypes === [] ? null : round($product['goods_in'], 4),
            'ending_stock' => $scope === null || $product['latest'] === [] ? null : round(collect($product['latest'])->sum('balance'), 4),
            'stock_observed_at' => $product['latest'] === [] ? null : collect($product['latest'])->max('observed_at'),
            'stock_sync_run_id' => $run->id,
            'input_version' => (string) $config['algorithm_version'],
            'created_at' => now(),
            'updated_at' => now(),
        ])->values()->all();

        $this->upsert($values, ['product_name', 'category_name', 'pos_sales_usage', 'waste_quantity', 'goods_in_quantity', 'ending_stock', 'stock_observed_at', 'stock_sync_run_id', 'input_version', 'updated_at']);
        $this->existing($pair, $date)
            ->whereNotIn('id', $this->idsFor($pair, $date, $values))
            ->update([
                'pos_sales_usage' => $posTypes === [] ? null : 0,
                'waste_quantity' => $wasteTypes === [] ? null : 0,
                'goods_in_quantity' => $goodsInTypes === [] ? null : 0,
                'ending_stock' => null,
                'stock_observed_at' => null,
                'stock_sync_run_id' => $run->id,
                'updated_at' => now(),
            ]);

        return count($values);
    }

    /** @return array<string, mixed> */
    private function identity(BranchEsbCode $pair, string $date, string $productCode, string $uom): array
    {
        return [
            'branch_id' => $pair->branch_id,
            'company_code' => (string) $pair->esb_comcode,
            'esb_branch_code' => (string) $pair->esb_branch_code,
            // Same storage format as Eloquent's date cast, so upserts match rows written by models.
            'metric_date' => (new OrderingDailyMetric)->fromDateTime(CarbonImmutable::parse($date)->startOfDay()),
            'product_code' => $productCode,
            'uom' => $uom,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $values
     * @param  list<string>  $update
     */
    private function upsert(array $values, array $update): void
    {
        foreach (array_chunk($values, 500) as $chunk) {
            OrderingDailyMetric::query()->upsert($chunk, ['branch_id', 'company_code', 'esb_branch_code', 'metric_date', 'product_code', 'uom'], $update);
        }
    }

    private function existing(BranchEsbCode $pair, string $date)
    {
        return OrderingDailyMetric::query()
            ->where('branch_id', $pair->branch_id)
            ->where('company_code', $pair->esb_comcode)
            ->where('esb_branch_code', $pair->esb_branch_code)
            ->whereDate('metric_date', $date);
    }

    /**
     * @param  list<array<string, mixed>>  $values
     * @return list<int>
     */
    private function idsFor(BranchEsbCode $pair, string $date, array $values): array
    {
        if ($values === []) {
            return [];
        }

        $keys = collect($values)->map(fn (array $value): string => $value['product_code'].'|'.$value['uom'])->all();

        return $this->existing($pair, $date)->get(['id', 'product_code', 'uom'])
            ->filter(fn (OrderingDailyMetric $metric): bool => in_array($metric->product_code.'|'.$metric->uom, $keys, true))
            ->pluck('id')->all();
    }

    /**
     * @param  'all'|list<string>|null  $scope
     */
    private function inScope(string $locationName, string $locationKey, string|array|null $scope): bool
    {
        if ($scope === null) {
            return false;
        }
        if ($scope === 'all') {
            return true;
        }

        $normalize = static fn (string $value): string => mb_strtolower(preg_replace('/\s+/', ' ', trim($value)) ?? '');

        return in_array($normalize($locationName), array_map($normalize, $scope), true)
            || in_array($normalize($locationKey), array_map($normalize, $scope), true);
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function lower(array $values): array
    {
        return array_map(fn (string $value): string => mb_strtolower(trim($value)), $values);
    }
}
