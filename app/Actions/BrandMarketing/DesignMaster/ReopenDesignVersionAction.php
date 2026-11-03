<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignVersionStatus;
use App\Models\MarketingDesignBundleVersion;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;

/**
 * Moves a returned version back to draft so the Designer can fix it (PRD §13.2).
 */
class ReopenDesignVersionAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    public function execute(User $actor, MarketingDesignBundleVersion $version): MarketingDesignBundleVersion
    {
        return DB::transaction(function () use ($actor, $version): MarketingDesignBundleVersion {
            $locked = $this->locks->version($version->marketing_design_bundle_id, $version->id);
            $this->access->authorize($actor, 'reopen', $locked);

            $from = $locked->status;
            $locked->update(['status' => MarketingDesignVersionStatus::Draft]);
            $this->audit->log(MarketingDesignAuditEvent::VersionReopened, $locked->bundle, $actor, $locked, fromStatus: $from->value, toStatus: $locked->status->value);

            return $locked;
        });
    }
}
