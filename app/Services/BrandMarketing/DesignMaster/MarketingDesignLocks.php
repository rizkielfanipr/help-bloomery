<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;

/**
 * Row locks for lifecycle Actions. The order is always bundle then version so concurrent Actions
 * cannot deadlock. Callers must already be inside a transaction.
 */
class MarketingDesignLocks
{
    public function bundle(int $bundleId): MarketingDesignBundle
    {
        return MarketingDesignBundle::query()->lockForUpdate()->findOrFail($bundleId);
    }

    public function version(int $bundleId, int $versionId): MarketingDesignBundleVersion
    {
        $bundle = $this->bundle($bundleId);

        return MarketingDesignBundleVersion::query()
            ->where('marketing_design_bundle_id', $bundle->id)
            ->lockForUpdate()
            ->findOrFail($versionId)
            ->setRelation('bundle', $bundle);
    }
}
