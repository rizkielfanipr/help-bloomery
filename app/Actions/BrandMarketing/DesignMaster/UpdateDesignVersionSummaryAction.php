<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignBundleVersion;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;

class UpdateDesignVersionSummaryAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    public function execute(User $actor, MarketingDesignBundleVersion $version, ?string $changeSummary): MarketingDesignBundleVersion
    {
        return DB::transaction(function () use ($actor, $version, $changeSummary): MarketingDesignBundleVersion {
            $locked = $this->locks->version($version->marketing_design_bundle_id, $version->id);
            $this->access->authorize($actor, 'editContent', $locked);

            $locked->update(['change_summary' => filled($changeSummary) ? trim((string) $changeSummary) : null]);
            $this->audit->log(MarketingDesignAuditEvent::VersionUpdated, $locked->bundle, $actor, $locked);

            return $locked;
        });
    }
}
