<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignFile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Evaluates Master Design policies directly instead of through Gate::check. The global Gate::before
 * lets SUPERADMIN skip a policy method entirely, which would also skip its status rules and allow,
 * for example, editing an archived bundle. Calling the method keeps those rules binding for everyone,
 * while SUPERADMIN still passes each permission check inside it.
 */
class MarketingDesignAccess
{
    public function allows(User $user, string $ability, MarketingDesignBundle|MarketingDesignFile $subject): bool
    {
        return (bool) Gate::getPolicyFor($subject)->{$ability}($user, $subject);
    }

    /** @throws AuthorizationException */
    public function authorize(User $user, string $ability, MarketingDesignBundle|MarketingDesignFile $subject): void
    {
        if (! $this->allows($user, $ability, $subject)) {
            throw new AuthorizationException;
        }
    }
}
