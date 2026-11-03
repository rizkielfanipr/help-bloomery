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

class ApproveDesignVersionAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    public function execute(User $actor, MarketingDesignBundleVersion $version, ?string $note = null): MarketingDesignBundleVersion
    {
        return DB::transaction(function () use ($actor, $version, $note): MarketingDesignBundleVersion {
            $locked = $this->locks->version($version->marketing_design_bundle_id, $version->id);
            $this->access->authorize($actor, 'approve', $locked);

            $from = $locked->status;
            $note = filled($note) ? trim((string) $note) : null;
            $locked->update(['status' => MarketingDesignVersionStatus::Approved, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);
            $this->audit->log(MarketingDesignAuditEvent::VersionApproved, $locked->bundle, $actor, $locked, fromStatus: $from->value, toStatus: $locked->status->value, reason: $note);

            return $locked;
        });
    }
}
