<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignVersionStatus;
use App\Models\MarketingDesignBundleVersion;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignNotifier;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignReadiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitDesignVersionAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignReadiness $readiness,
        private readonly MarketingDesignAuditLogger $audit,
        private readonly MarketingDesignNotifier $notifier,
    ) {}

    /** @throws ValidationException */
    public function execute(User $actor, MarketingDesignBundleVersion $version): MarketingDesignBundleVersion
    {
        $submitted = DB::transaction(function () use ($actor, $version): MarketingDesignBundleVersion {
            $locked = $this->locks->version($version->marketing_design_bundle_id, $version->id);
            $this->access->authorize($actor, 'submit', $locked);
            $this->readiness->ensureReady($locked);

            $from = $locked->status;
            $locked->update(['status' => MarketingDesignVersionStatus::InReview, 'submitted_by' => $actor->id, 'submitted_at' => now()]);
            $this->audit->log(MarketingDesignAuditEvent::VersionSubmitted, $locked->bundle, $actor, $locked, fromStatus: $from->value, toStatus: $locked->status->value);

            return $locked;
        });

        $this->notifier->submitted($submitted, $actor);

        return $submitted;
    }
}
