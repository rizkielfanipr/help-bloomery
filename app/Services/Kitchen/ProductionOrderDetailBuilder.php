<?php

namespace App\Services\Kitchen;

use App\Models\EsbProductionOrder;
use App\Models\EsbProductionOrderSyncRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only detail of one ESB Production Order snapshot (docs/products/kitchen/production-schedule/prd.md §32,
 * §34.3). The record is re-queried by its snapshot ID and re-authorized on every call; nothing
 * from the browser other than that ID is used, and ESB is never called here.
 */
class ProductionOrderDetailBuilder
{
    /**
     * @return array{order: EsbProductionOrder, notSeenInLatestSync: bool, detailsPending: bool}
     *
     * @throws AuthorizationException
     */
    public function build(User $user, int $orderId): array
    {
        $order = EsbProductionOrder::query()
            ->with([
                'branch:id,brand_id,name,is_active',
                'details' => fn (HasMany $query): HasMany => $query->orderBy('sequence')->orderBy('id'),
            ])
            // Kitchen-Brand only even when Gate::before lets SUPERADMIN past the Policy (prd.md §28.6).
            ->whereHas('branch', fn (Builder $query): Builder => $query->kitchenBrand())
            ->findOrFail($orderId);

        Gate::forUser($user)->authorize('view', $order);

        return [
            'order' => $order,
            'notSeenInLatestSync' => $this->notSeenInLatestSync($order),
            'detailsPending' => $order->details_synced_at === null,
        ];
    }

    /**
     * True when a complete list read of the order's mapping, covering its date, started after the
     * order was last seen — i.e. ESB no longer returned it (decision K11). It stays visible.
     */
    private function notSeenInLatestSync(EsbProductionOrder $order): bool
    {
        if ($order->branch_esb_code_id === null || $order->source_last_seen_at === null) {
            return false;
        }

        return EsbProductionOrderSyncRun::query()
            ->where('branch_esb_code_id', $order->branch_esb_code_id)
            ->whereDate('date_from', '<=', $order->production_order_date->toDateString())
            ->whereDate('date_to', '>=', $order->production_order_date->toDateString())
            ->whereNotNull('list_completed_at')
            ->where('started_at', '>', $order->source_last_seen_at)
            ->exists();
    }
}
