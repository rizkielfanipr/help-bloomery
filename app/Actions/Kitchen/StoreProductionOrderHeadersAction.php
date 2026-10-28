<?php

namespace App\Actions\Kitchen;

use App\Models\BranchEsbCode;
use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Upserts one page of normalized Production Order headers for one mapping
 * (docs/products/kitchen/production-schedule/prd.md §28.5, §30.2, §31). The same payload is a no-op apart from
 * `source_last_seen_at`; a changed payload updates the same row. An order already owned by another
 * local Branch is counted as a conflict and never moved. Nothing is deleted here.
 *
 * Returns the snapshot IDs whose details must be (re)read: new, changed, or never hydrated.
 */
class StoreProductionOrderHeadersAction
{
    /**
     * @param  list<array<string, mixed>>  $orders  rows from `EsbProductionOrderNormalizer::header()`
     * @return list<int>
     */
    public function execute(EsbProductionOrderSyncRun $run, BranchEsbCode $mapping, array $orders): array
    {
        $companyCode = mb_strtoupper(trim((string) $mapping->esb_comcode));
        $orders = collect($orders)->keyBy('production_order_num');

        return DB::transaction(function () use ($run, $mapping, $companyCode, $orders): array {
            $existing = EsbProductionOrder::query()
                ->where('company_code', $companyCode)
                ->whereIn('production_order_num', $orders->keys()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('production_order_num');

            $now = now();
            $needsDetails = [];
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0];

            foreach ($orders as $number => $order) {
                $snapshot = $existing->get($number);

                if ($snapshot !== null && (int) $snapshot->branch_id !== (int) $mapping->branch_id) {
                    $counts['conflicts']++;
                    Log::warning('Kitchen Production Order owned by another Branch mapping.', [
                        'sync_run_id' => $run->id, 'mapping_id' => $mapping->id, 'company_code' => $companyCode,
                        'production_order_num' => $number, 'owner_branch_id' => $snapshot->branch_id, 'mapping_branch_id' => $mapping->branch_id,
                    ]);

                    continue;
                }

                if ($snapshot !== null && $snapshot->payload_hash === $order['payload_hash']) {
                    $snapshot->update(['source_last_seen_at' => $now]);
                    $counts['unchanged']++;

                    if ($snapshot->details_synced_at === null) {
                        $needsDetails[] = $snapshot->id;
                    }

                    continue;
                }

                $attributes = [
                    ...$order,
                    'branch_esb_code_id' => $snapshot?->branch_esb_code_id ?? $mapping->id,
                    'branch_id' => $mapping->branch_id,
                    'company_code' => $companyCode,
                    'source_fetched_at' => $now,
                    'source_last_seen_at' => $now,
                ];

                if ($snapshot === null) {
                    $snapshot = EsbProductionOrder::query()->create($attributes);
                    $counts['created']++;
                } else {
                    $snapshot->update($attributes);
                    $counts['updated']++;
                }

                $needsDetails[] = $snapshot->id;
            }

            $run->update([
                'headers_received' => $run->headers_received + $orders->count(),
                'headers_created' => $run->headers_created + $counts['created'],
                'headers_updated' => $run->headers_updated + $counts['updated'],
                'headers_unchanged' => $run->headers_unchanged + $counts['unchanged'],
                'conflicts' => $run->conflicts + $counts['conflicts'],
            ]);

            return $needsDetails;
        });
    }
}
