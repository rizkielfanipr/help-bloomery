<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignAuditLog;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\User;

/**
 * Writes the append-only audit (PRD §20). Every event except downloads also marks the bundle as
 * updated, because the index shows when its content last changed.
 */
class MarketingDesignAuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata  Never signed URLs, paths, or credentials.
     */
    public function log(
        MarketingDesignAuditEvent $event,
        MarketingDesignBundle $bundle,
        User $actor,
        ?MarketingDesignBundleVersion $version = null,
        ?MarketingDesignItem $item = null,
        ?MarketingDesignFile $file = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $reason = null,
        array $metadata = [],
    ): MarketingDesignAuditLog {
        if (! $event->isDownload()) {
            $bundle->touch();
        }

        return MarketingDesignAuditLog::query()->create([
            'marketing_design_bundle_id' => $bundle->id,
            'marketing_design_bundle_version_id' => $version?->id,
            'marketing_design_item_id' => $item?->id,
            'marketing_design_file_id' => $file?->id,
            'event' => $event,
            'actor_id' => $actor->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
