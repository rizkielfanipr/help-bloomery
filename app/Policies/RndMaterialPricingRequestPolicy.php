<?php

namespace App\Policies;

use App\Models\RndMaterialPricingRequest;
use App\Models\User;

/**
 * Finance → Product Pricelist (§17). Confirming a Pricelist is the Finance approval.
 */
class RndMaterialPricingRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view material pricelist');
    }

    public function view(User $user, RndMaterialPricingRequest $request): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user, RndMaterialPricingRequest $request): bool
    {
        return $this->viewAny($user) && $user->can('manage material pricelist');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RndMaterialPricingRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, RndMaterialPricingRequest $request): bool
    {
        return false;
    }
}
