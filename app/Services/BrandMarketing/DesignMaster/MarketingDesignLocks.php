<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Models\MarketingDesignBundle;

/**
 * Row lock for Master Design Actions, so a concurrent archive cannot interleave with an edit.
 * Callers must already be inside a transaction.
 */
class MarketingDesignLocks
{
    public function bundle(int $bundleId): MarketingDesignBundle
    {
        return MarketingDesignBundle::query()->lockForUpdate()->findOrFail($bundleId);
    }
}
