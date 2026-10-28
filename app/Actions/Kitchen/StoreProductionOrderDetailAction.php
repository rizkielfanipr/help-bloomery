<?php

namespace App\Actions\Kitchen;

use App\Models\EsbProductionOrder;
use Illuminate\Support\Facades\DB;

/**
 * Stores the detail rows of one Production Order in one transaction
 * (docs/products/kitchen/production-schedule/prd.md §30.4). Rows are matched by their ESB detail ID, so a repeat is
 * idempotent. Rows missing from the response are kept until the contract proves the detail
 * response is always complete; the header itself is never deleted.
 */
class StoreProductionOrderDetailAction
{
    /**
     * @param  array{header: array<string, mixed>, details: list<array<string, mixed>>}  $order  from `EsbProductionOrderNormalizer::order()`
     */
    public function execute(EsbProductionOrder $snapshot, array $order): EsbProductionOrder
    {
        return DB::transaction(function () use ($snapshot, $order): EsbProductionOrder {
            $locked = EsbProductionOrder::query()->whereKey($snapshot->getKey())->lockForUpdate()->firstOrFail();

            foreach ($order['details'] as $detail) {
                $locked->details()->updateOrCreate(
                    ['external_detail_id' => $detail['external_detail_id']],
                    collect($detail)->except('external_detail_id')->all(),
                );
            }

            $actors = collect($order['header'])
                ->only(['created_by_name', 'esb_created_at', 'edited_by_name', 'esb_edited_at', 'authorized_by_name', 'esb_authorized_at'])
                ->filter(fn (mixed $value): bool => $value !== null)
                ->all();

            $locked->update([...$actors, 'details_synced_at' => now()]);

            return $locked;
        });
    }
}
