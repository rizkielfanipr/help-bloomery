<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignBundleStatus;
use App\Models\MarketingDesignBundle;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;

class RestoreDesignBundleAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    public function execute(User $actor, MarketingDesignBundle $bundle): MarketingDesignBundle
    {
        return DB::transaction(function () use ($actor, $bundle): MarketingDesignBundle {
            $locked = $this->locks->bundle($bundle->id);
            $this->access->authorize($actor, 'restore', $locked);

            $locked->update([
                'status' => MarketingDesignBundleStatus::Active,
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
                'updated_by' => $actor->id,
            ]);

            $this->audit->log(MarketingDesignAuditEvent::BundleRestored, $locked, $actor, fromStatus: MarketingDesignBundleStatus::Archived->value, toStatus: MarketingDesignBundleStatus::Active->value);

            return $locked;
        });
    }
}
