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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestDesignVersionRevisionAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
        private readonly MarketingDesignNotifier $notifier,
    ) {}

    /** @throws ValidationException */
    public function execute(User $actor, MarketingDesignBundleVersion $version, string $reason): MarketingDesignBundleVersion
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan revisi wajib diisi.']);
        }

        $returned = DB::transaction(function () use ($actor, $version, $reason): MarketingDesignBundleVersion {
            $locked = $this->locks->version($version->marketing_design_bundle_id, $version->id);
            $this->access->authorize($actor, 'requestRevision', $locked);

            $from = $locked->status;
            $locked->update(['status' => MarketingDesignVersionStatus::RevisionRequired, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $reason]);
            $this->audit->log(MarketingDesignAuditEvent::VersionRevisionRequired, $locked->bundle, $actor, $locked, fromStatus: $from->value, toStatus: $locked->status->value, reason: $reason);

            return $locked;
        });

        $this->notifier->revisionRequired($returned, $actor);

        return $returned;
    }
}
