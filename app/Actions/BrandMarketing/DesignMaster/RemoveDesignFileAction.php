<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignFile;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;

/**
 * Detaches a file from a draft by soft delete. The object stays because an older version may
 * reference the same path; cleanup needs a separate reference audit (PRD §18.3).
 */
class RemoveDesignFileAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    public function execute(User $actor, MarketingDesignFile $file): void
    {
        DB::transaction(function () use ($actor, $file): void {
            $item = $file->item;
            $version = $this->locks->version($item->version->marketing_design_bundle_id, $item->marketing_design_bundle_version_id);
            $this->access->authorize($actor, 'uploadFile', $version);

            $target = MarketingDesignFile::query()->where('marketing_design_item_id', $item->id)->findOrFail($file->id);
            $target->delete();

            $this->audit->log(MarketingDesignAuditEvent::FileRemoved, $version->bundle, $actor, $version, $item, $target, metadata: ['role' => $target->role->value, 'original_name' => $target->original_name]);
        });
    }
}
