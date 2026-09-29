<?php

namespace App\Policies;

use App\Models\RndBomChangeLog;
use App\Models\User;

/**
 * Permission strings match docs/rnd-bom-adjustment-prd.md §17.
 */
class RndBomChangeLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view bom adjustment history');
    }

    public function view(User $user, RndBomChangeLog $log): bool
    {
        return $user->can('view bom adjustment history');
    }

    /**
     * Reconciliation only makes sense while the mutation's true outcome is still unknown
     * (docs/rnd-bom-adjustment-prd.md §14).
     */
    public function reconcile(User $user, RndBomChangeLog $log): bool
    {
        return $user->can('reconcile bom adjustments') && $log->status->isUncertain();
    }
}
