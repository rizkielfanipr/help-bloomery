<?php

namespace App\Policies;

use App\Models\EsbProductionOrder;
use App\Models\User;

/**
 * docs/products/kitchen/production-schedule/prd.md §32. ESB Production Order snapshots reuse the Jadwal
 * Produksi permission and Branch scope (decision K7). There is deliberately no create, update,
 * delete, or cancel ability: the snapshot is read-only and never written back to ESB. No role is
 * named here. `Gate::before` lets SUPERADMIN through every ability, so the Kitchen-Brand scope is
 * also enforced by the queries and the detail builder (§28.6).
 */
class EsbProductionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view kitchen production schedules');
    }

    /**
     * "Sync Data" (prd.md §39, K21): only queues a read-only sync, never writes to ESB.
     */
    public function sync(User $user): bool
    {
        return $this->viewAny($user) && $user->can('sync kitchen production orders');
    }

    public function view(User $user, EsbProductionOrder $order): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return (bool) $order->branch?->hasKitchenBrand()
            && ($user->canAccessAllBranches()
                || $user->can('view all branch kitchen production schedules')
                || $user->canAccessBranch($order->branch_id));
    }
}
