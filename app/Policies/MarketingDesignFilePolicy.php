<?php

namespace App\Policies;

use App\Models\MarketingDesignFile;
use App\Models\User;

/**
 * Access to a design file (PRD §16): the user must see the bundle and hold the download permission.
 * Inline preview is limited to images and PDFs.
 */
class MarketingDesignFilePolicy
{
    public function __construct(private readonly MarketingDesignBundlePolicy $bundles) {}

    public function preview(User $user, MarketingDesignFile $file): bool
    {
        return $file->isPreviewable() && $this->download($user, $file);
    }

    public function download(User $user, MarketingDesignFile $file): bool
    {
        $bundle = $file->item?->bundle;

        return $bundle !== null && $this->bundles->view($user, $bundle) && $user->can('download design master files');
    }
}
