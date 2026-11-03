<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignItem;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;

/**
 * Removes an item from a draft by soft delete. Its files and objects stay untouched.
 */
class RemoveDesignItemAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    public function execute(User $actor, MarketingDesignItem $item): void
    {
        DB::transaction(function () use ($actor, $item): void {
            $version = $this->locks->version($item->version->marketing_design_bundle_id, $item->marketing_design_bundle_version_id);
            $this->access->authorize($actor, 'editContent', $version);

            $target = $version->items()->findOrFail($item->id);
            $target->delete();

            $this->audit->log(MarketingDesignAuditEvent::ItemRemoved, $version->bundle, $actor, $version, $target, metadata: ['title' => $target->title]);
        });
    }
}
