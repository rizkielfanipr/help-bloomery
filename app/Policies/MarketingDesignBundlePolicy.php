<?php

namespace App\Policies;

use App\Enums\MarketingDesignVersionStatus;
use App\Models\MarketingDesignBundle;
use App\Models\User;

/**
 * docs/products/brand-marketing/design-master-library/prd.md §16: every ability checks the permission
 * and the bundle state on the server; hiding a button is never the security boundary.
 */
class MarketingDesignBundlePolicy
{
    private const INTERNAL_PERMISSIONS = [
        'create design master bundles',
        'edit design master bundles',
        'upload design master files',
        'review design master bundles',
        'publish design master bundles',
        'archive design master bundles',
    ];

    public function viewAny(User $user): bool
    {
        return $user->can('view design master bundles');
    }

    /** Internal users may see unpublished and superseded versions and archived bundles. */
    public function viewUnpublished(User $user): bool
    {
        return $this->viewAny($user) && $user->canAny(self::INTERNAL_PERMISSIONS);
    }

    public function view(User $user, MarketingDesignBundle $bundle): bool
    {
        if ($this->viewUnpublished($user)) {
            return true;
        }

        return $this->viewAny($user)
            && $bundle->isActive()
            && $bundle->versions()->where('status', MarketingDesignVersionStatus::Published)->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('create design master bundles');
    }

    public function update(User $user, MarketingDesignBundle $bundle): bool
    {
        return $this->viewAny($user) && $user->can('edit design master bundles') && $bundle->isActive();
    }

    public function createRevision(User $user, MarketingDesignBundle $bundle): bool
    {
        return $this->update($user, $bundle);
    }

    public function archive(User $user, MarketingDesignBundle $bundle): bool
    {
        return $this->viewAny($user) && $user->can('archive design master bundles') && $bundle->isActive();
    }

    public function restore(User $user, MarketingDesignBundle $bundle): bool
    {
        return $this->viewAny($user) && $user->can('archive design master bundles') && ! $bundle->isActive();
    }
}
