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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Replaces the design file of an item (PRD §18.3). The new object is written outside the
 * transaction so no row lock waits on storage, and removed again if the record cannot be saved.
 * The previous record is soft deleted and its object stays.
 */
class ReplaceDesignFileAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignFileStore $store,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    /** @throws ValidationException */
    public function execute(User $actor, MarketingDesignItem $item, UploadedFile $file): MarketingDesignFile
    {
        $this->access->authorize($actor, 'uploadFile', $item->bundle);
        $stored = $this->store->store($file, $item->bundle);

        try {
            return DB::transaction(function () use ($actor, $item, $stored): MarketingDesignFile {
                $bundle = $this->locks->bundle($item->marketing_design_bundle_id);
                $this->access->authorize($actor, 'uploadFile', $bundle);

                $target = $bundle->items()->findOrFail($item->id);
                $previous = MarketingDesignFile::query()->where('marketing_design_item_id', $target->id)->lockForUpdate()->get();
                $previous->each->delete();

                $created = $target->file()->create($stored + ['uploaded_by' => $actor->id, 'uploaded_at' => now()]);
                $this->audit->log(MarketingDesignAuditEvent::FileReplaced, $bundle, $actor, $target, $created, metadata: [
                    'original_name' => $created->original_name,
                    'replaced_file_ids' => $previous->modelKeys(),
                ]);

                return $created;
            });
        } catch (Throwable $exception) {
            $this->store->discard($stored['path']);

            throw $exception;
        }
    }
}
