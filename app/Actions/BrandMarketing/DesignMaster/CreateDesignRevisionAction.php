<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignVersionStatus;
use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignBundleVersion;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts a new draft from the published version (PRD §11.6, §11.8). Item metadata is copied and
 * every file row explicitly references the same object through reused_from_file_id; no binary is copied.
 */
class CreateDesignRevisionAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    /** @throws ValidationException */
    public function execute(User $actor, MarketingDesignBundle $bundle, string $changeSummary): MarketingDesignBundleVersion
    {
        $changeSummary = trim($changeSummary);

        if ($changeSummary === '') {
            throw ValidationException::withMessages(['change_summary' => 'Ringkasan perubahan wajib diisi.']);
        }

        return DB::transaction(function () use ($actor, $bundle, $changeSummary): MarketingDesignBundleVersion {
            $locked = $this->locks->bundle($bundle->id);
            $this->access->authorize($actor, 'createRevision', $locked);

            $versions = $locked->versions()->lockForUpdate()->get();
            $published = $versions->firstWhere('status', MarketingDesignVersionStatus::Published);

            if ($published === null) {
                throw ValidationException::withMessages(['version' => 'Revisi hanya dapat dibuat dari version published.']);
            }

            if ($versions->contains(fn (MarketingDesignBundleVersion $version): bool => $version->status->isOpen())) {
                throw ValidationException::withMessages(['version' => 'Selesaikan version yang masih berjalan sebelum membuat revisi baru.']);
            }

            $revision = $locked->versions()->create([
                'version_number' => (int) $versions->max('version_number') + 1,
                'status' => MarketingDesignVersionStatus::Draft,
                'cloned_from_version_id' => $published->id,
                'change_summary' => $changeSummary,
                'created_by' => $actor->id,
            ]);

            $published->load('items.files');
            $published->items->each(fn (MarketingDesignItem $item) => $this->cloneItem($item, $revision));

            $this->audit->log(MarketingDesignAuditEvent::VersionCreated, $locked, $actor, $revision, toStatus: $revision->status->value, metadata: ['cloned_from_version_id' => $published->id]);

            return $revision;
        });
    }

    private function cloneItem(MarketingDesignItem $item, MarketingDesignBundleVersion $revision): void
    {
        $clone = $item->replicate(['marketing_design_bundle_version_id', 'cloned_from_item_id']);
        $clone->marketing_design_bundle_version_id = $revision->id;
        $clone->cloned_from_item_id = $item->id;
        $clone->save();

        $item->files->each(function (MarketingDesignFile $file) use ($clone): void {
            $reused = $file->replicate(['marketing_design_item_id', 'reused_from_file_id']);
            $reused->marketing_design_item_id = $clone->id;
            $reused->reused_from_file_id = $file->id;
            $reused->save();
        });
    }
}
