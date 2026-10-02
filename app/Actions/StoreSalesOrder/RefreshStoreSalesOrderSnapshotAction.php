<?php

namespace App\Actions\StoreSalesOrder;

use App\Models\StoreSalesOrder;
use App\Models\StoreSalesOrderActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * docs/store-sales-order-prd.md §14 "Refresh dari ESB": only overwrites the ESB snapshot columns
 * and `last_verified_at`. Operational information, Kebutuhan Produk, attachment, and operational
 * status are never touched. If the lookup throws (timeout, connection failure, not found), the
 * exception propagates and the old snapshot is left exactly as it was — this Action performs no
 * writes until the lookup has already succeeded.
 */
class RefreshStoreSalesOrderSnapshotAction
{
    use MapsStoreSalesOrderSnapshot;

    public function __construct(private readonly LookupStoreSalesOrderAction $lookup) {}

    public function execute(StoreSalesOrder $order, User $actor): StoreSalesOrder
    {
        ['mapping' => $mapping, 'snapshot' => $snapshot] = $this->lookup->execute($order->branch, $order->product_sales_number);

        return DB::transaction(function () use ($order, $mapping, $snapshot, $actor): StoreSalesOrder {
            $order->update($this->snapshotAttributes($order->branch, $mapping, $snapshot));

            $order->activities()->create([
                'activity_type' => StoreSalesOrderActivity::TYPE_SNAPSHOT_REFRESHED,
                'created_by' => $actor->id,
            ]);

            return $order->refresh();
        });
    }
}
