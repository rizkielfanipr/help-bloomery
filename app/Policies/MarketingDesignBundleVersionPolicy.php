<?php

namespace App\Policies;

use App\Enums\MarketingDesignVersionStatus;
use App\Models\MarketingDesignBundleVersion;
use App\Models\User;

/**
 * Status-aware abilities of one version (PRD §13.2, §16). Actions evaluate these inside the row lock,
 * so a repeated click sees the new status and is refused.
 */
class MarketingDesignBundleVersionPolicy
{
    public function __construct(private readonly MarketingDesignBundlePolicy $bundles) {}

    public function view(User $user, MarketingDesignBundleVersion $version): bool
    {
        if ($this->bundles->viewUnpublished($user)) {
            return true;
        }

        return $this->bundles->viewAny($user)
            && $version->status === MarketingDesignVersionStatus::Published
            && $version->bundle->isActive();
    }

    public function editContent(User $user, MarketingDesignBundleVersion $version): bool
    {
        return $this->isDraftOfActiveBundle($version) && $this->bundles->viewAny($user) && $user->can('edit design master bundles');
    }

    public function uploadFile(User $user, MarketingDesignBundleVersion $version): bool
    {
        return $this->isDraftOfActiveBundle($version) && $this->bundles->viewAny($user) && $user->can('upload design master files');
    }

    public function submit(User $user, MarketingDesignBundleVersion $version): bool
    {
        return $this->editContent($user, $version);
    }

    public function reopen(User $user, MarketingDesignBundleVersion $version): bool
    {
        return $version->status === MarketingDesignVersionStatus::RevisionRequired
            && $version->bundle->isActive()
            && $this->bundles->viewAny($user)
            && $user->can('edit design master bundles');
    }

    public function approve(User $user, MarketingDesignBundleVersion $version): bool
    {
        return $version->status === MarketingDesignVersionStatus::InReview
            && $version->bundle->isActive()
            && $this->bundles->viewAny($user)
            && $user->can('review design master bundles');
    }

    public function requestRevision(User $user, MarketingDesignBundleVersion $version): bool
    {
        if (! $version->bundle->isActive() || ! $this->bundles->viewAny($user)) {
            return false;
        }

        return match ($version->status) {
            MarketingDesignVersionStatus::InReview => $user->can('review design master bundles'),
            MarketingDesignVersionStatus::Approved => $user->canAny(['review design master bundles', 'publish design master bundles']),
            default => false,
        };
    }

    public function publish(User $user, MarketingDesignBundleVersion $version): bool
    {
        return $version->status === MarketingDesignVersionStatus::Approved
            && $version->bundle->isActive()
            && $this->bundles->viewAny($user)
            && $user->can('publish design master bundles');
    }

    private function isDraftOfActiveBundle(MarketingDesignBundleVersion $version): bool
    {
        return $version->isDraft() && $version->bundle->isActive();
    }
}
