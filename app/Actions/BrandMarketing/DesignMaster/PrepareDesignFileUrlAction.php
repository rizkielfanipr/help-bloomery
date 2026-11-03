<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignFile;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignFileStore;

/**
 * Authorizes first, then creates the temporary URL (PRD §16). Downloads are audited with the actor
 * and file ID; the URL itself is never recorded.
 */
class PrepareDesignFileUrlAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignFileStore $store,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    public function preview(User $actor, MarketingDesignFile $file): string
    {
        $this->access->authorize($actor, 'preview', $file);

        return $this->store->temporaryUrl($file, inline: true);
    }

    public function download(User $actor, MarketingDesignFile $file): string
    {
        $this->access->authorize($actor, 'download', $file);

        $url = $this->store->temporaryUrl($file, inline: false);
        $this->audit->log(MarketingDesignAuditEvent::FileDownloaded, $file->item->bundle, $actor, $file->item, $file);

        return $url;
    }
}
