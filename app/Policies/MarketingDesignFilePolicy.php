<?php

namespace App\Policies;

use App\Enums\MarketingDesignFileRole;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignFile;
use App\Models\User;

/**
 * File access per role and version status (PRD §16). Source access is never implied by the view or
 * production permissions.
 */
class MarketingDesignFilePolicy
{
    public function __construct(
        private readonly MarketingDesignBundlePolicy $bundles,
        private readonly MarketingDesignBundleVersionPolicy $versions,
    ) {}

    public function preview(User $user, MarketingDesignFile $file): bool
    {
        $version = $this->version($file);

        return $version !== null
            && $file->role === MarketingDesignFileRole::Preview
            && $this->versions->view($user, $version);
    }

    public function download(User $user, MarketingDesignFile $file): bool
    {
        $version = $this->version($file);

        if ($version === null || ! $this->versions->view($user, $version)) {
            return false;
        }

        return in_array($file->role, $this->downloadableRoles($user), true);
    }

    /** @return list<MarketingDesignFileRole> */
    public function downloadableRoles(User $user): array
    {
        return array_values(array_filter(MarketingDesignFileRole::cases(), fn (MarketingDesignFileRole $role): bool => match ($role) {
            MarketingDesignFileRole::Preview => true,
            MarketingDesignFileRole::Production => $user->can('download design master production files'),
            MarketingDesignFileRole::Source => $user->can('download design master source files'),
            MarketingDesignFileRole::Supporting => $this->bundles->viewUnpublished($user),
        }));
    }

    private function version(MarketingDesignFile $file): ?MarketingDesignBundleVersion
    {
        return $file->item?->version;
    }
}
