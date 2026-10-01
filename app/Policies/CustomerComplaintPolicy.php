<?php

namespace App\Policies;

use App\Models\CustomerComplaint;
use App\Models\User;

/**
 * docs/customer-complaints-prd.md §12. Branch scope mirrors the same mechanism every other
 * branch-scoped Policy in this app uses (e.g. StockCardPolicy, RndProjectTaskPolicy):
 * `User::canAccessBranch()` / `canAccessAllBranches()`. A submitter can always see their own
 * complaint regardless of branch access, since they are the one who reported it.
 */
class CustomerComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view any customer complaints');
    }

    public function view(User $user, CustomerComplaint $complaint): bool
    {
        if ($complaint->submitted_by === $user->id) {
            return true;
        }

        return $user->can('view customer complaints') && $user->canAccessBranch($complaint->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('create customer complaints');
    }

    public function update(User $user, CustomerComplaint $complaint): bool
    {
        return $user->can('update customer complaints') && $user->canAccessBranch($complaint->branch_id);
    }

    public function delete(User $user, CustomerComplaint $complaint): bool
    {
        return $user->can('delete customer complaints') && $user->canAccessBranch($complaint->branch_id);
    }
}
