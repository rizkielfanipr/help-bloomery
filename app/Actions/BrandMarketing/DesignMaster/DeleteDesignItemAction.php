<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignFileStore;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;

/**
 * Permanently deletes an item with its current and replaced file records (PRD §11.13). The audit
 * keeps who deleted which item. Objects are removed only after the records are gone, so a storage
 * failure can leave an orphan object but never a record pointing at a missing file.
 */
class DeleteDesignItemAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignFileStore $store,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    public function execute(User $actor, MarketingDesignItem $item): void
    {
        $files = DB::transaction(function () use ($actor, $item): array {
            $bundle = $this->locks->bundle($item->marketing_design_bundle_id);
            $this->access->authorize($actor, 'editContent', $bundle);

            $target = $bundle->items()->findOrFail($item->id);
            $files = MarketingDesignFile::withTrashed()->where('marketing_design_item_id', $target->id)->lockForUpdate()->get();

            $this->audit->log(MarketingDesignAuditEvent::ItemDeleted, $bundle, $actor, metadata: [
                'item_id' => $target->id,
                'title' => $target->title,
                'type' => $target->type,
                'deleted_file_count' => $files->count(),
            ]);

            $files->each->forceDelete();
            $target->delete();

            return $files->map(fn (MarketingDesignFile $file): array => ['disk' => $file->disk, 'path' => $file->path])->all();
        });

        $this->store->purge($files);
    }
}
