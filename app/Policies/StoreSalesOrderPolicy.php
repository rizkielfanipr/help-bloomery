<?php

namespace App\Policies;

use App\Models\StoreSalesOrder;
use App\Models\User;

/**
 * docs/store-sales-order-prd.md §16. Branch scope mirrors the same mechanism every other
 * branch-scoped Policy in this app uses (canAccessBranch/canAccessAllBranches). The submitter can
 * always see their own record regardless of branch access, since they are the one who created it.
 */
class StoreSalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view any store sales orders');
    }

    public function view(User $user, StoreSalesOrder $order): bool
    {
        if ($order->submitted_by === $user->id) {
            return true;
        }

        return $user->can('view store sales orders') && $user->canAccessBranch($order->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('create store sales orders');
    }

    public function update(User $user, StoreSalesOrder $order): bool
    {
        return $user->can('update store sales orders') && $user->canAccessBranch($order->branch_id);
    }

    public function updateStatus(User $user, StoreSalesOrder $order): bool
    {
        return $user->can('update store sales order status') && $user->canAccessBranch($order->branch_id);
    }

    public function delete(User $user, StoreSalesOrder $order): bool
    {
        return $user->can('delete store sales orders') && $user->canAccessBranch($order->branch_id);
    }
}
