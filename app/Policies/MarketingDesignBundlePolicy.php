<?php

namespace App\Policies;

use App\Models\MarketingDesignBundle;
use App\Models\User;

/**
 * docs/products/brand-marketing/design-master-library/prd.md §16: every ability checks the permission
 * and the bundle status on the server; hiding a button is never the security boundary.
 */
class MarketingDesignBundlePolicy
{
    private const INTERNAL_PERMISSIONS = [
        'create design master bundles',
        'edit design master bundles',
        'upload design master files',
        'archive design master bundles',
    ];

    public function viewAny(User $user): bool
    {
        return $user->can('view design master bundles');
    }

    /** Internal users may see archived bundles. */
    public function viewArchived(User $user): bool
    {
        return $this->viewAny($user) && $user->canAny(self::INTERNAL_PERMISSIONS);
    }

    public function view(User $user, MarketingDesignBundle $bundle): bool
    {
        return $this->viewArchived($user) || ($this->viewAny($user) && $bundle->isActive());
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('create design master bundles');
    }

    public function update(User $user, MarketingDesignBundle $bundle): bool
    {
        return $bundle->isActive() && $this->viewAny($user) && $user->can('edit design master bundles');
    }

    public function editContent(User $user, MarketingDesignBundle $bundle): bool
    {
        return $this->update($user, $bundle);
    }

    public function uploadFile(User $user, MarketingDesignBundle $bundle): bool
    {
        return $bundle->isActive() && $this->viewAny($user) && $user->can('upload design master files');
    }

    public function archive(User $user, MarketingDesignBundle $bundle): bool
    {
        return $bundle->isActive() && $this->viewAny($user) && $user->can('archive design master bundles');
    }

    public function restore(User $user, MarketingDesignBundle $bundle): bool
    {
        return ! $bundle->isActive() && $this->viewAny($user) && $user->can('archive design master bundles');
    }
}
