<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignFileRole;
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
 * Uploads a file to a draft item, optionally replacing an existing file (PRD §18.3). The object is
 * written outside the transaction so no row lock waits on storage; if the record cannot be saved,
 * the new object is removed. A replaced file keeps its object.
 */
class UploadDesignFileAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignFileStore $store,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    /** @throws ValidationException */
    public function execute(User $actor, MarketingDesignItem $item, MarketingDesignFileRole $role, UploadedFile $file, ?MarketingDesignFile $replacing = null): MarketingDesignFile
    {
        $this->access->authorize($actor, 'uploadFile', $item->version);

        if ($replacing !== null && ($replacing->marketing_design_item_id !== $item->id || $replacing->role !== $role)) {
            throw ValidationException::withMessages(['file' => 'File pengganti harus berada pada item dan role yang sama.']);
        }

        $stored = $this->store->store($file, $item, $role);

        try {
            return DB::transaction(function () use ($actor, $item, $role, $stored, $replacing): MarketingDesignFile {
                $version = $this->locks->version($item->version->marketing_design_bundle_id, $item->marketing_design_bundle_version_id);
                $this->access->authorize($actor, 'uploadFile', $version);

                $target = $version->items()->findOrFail($item->id);
                $created = $target->files()->create($stored + [
                    'role' => $role,
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => now(),
                ]);

                if ($replacing !== null) {
                    $target->files()->findOrFail($replacing->id)->delete();
                }

                $this->audit->log(
                    $replacing !== null ? MarketingDesignAuditEvent::FileReplaced : MarketingDesignAuditEvent::FileUploaded,
                    $version->bundle,
                    $actor,
                    $version,
                    $target,
                    $created,
                    metadata: ['role' => $role->value, 'original_name' => $created->original_name, 'replaced_file_id' => $replacing?->id],
                );

                return $created;
            });
        } catch (Throwable $exception) {
            $this->store->discard($stored['path']);

            throw $exception;
        }
    }
}
