<?php

namespace App\Policies;

use App\Models\RndBomCatalog;
use App\Models\User;

/**
 * Permission strings match docs/rnd-bom-adjustment-prd.md §17 — the same `view bill of
 * materials` / `edit bill of materials` permissions already used by the Project inline editor,
 * so BOM Adjustment and Project stay on one authorization contract.
 */
class RndBomCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view bill of materials');
    }

    public function view(User $user, RndBomCatalog $catalog): bool
    {
        return $user->can('view bill of materials');
    }

    public function update(User $user, RndBomCatalog $catalog): bool
    {
        return $user->can('edit bill of materials');
    }
}
