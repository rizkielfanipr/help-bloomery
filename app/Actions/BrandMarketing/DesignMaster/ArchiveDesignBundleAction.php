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
use Illuminate\Validation\ValidationException;

/**
 * Archiving only changes the bundle status: items, files, objects, and audit stay (PRD §11.13).
 */
class ArchiveDesignBundleAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignAuditLogger $audit,
    ) {}

    /** @throws ValidationException */
    public function execute(User $actor, MarketingDesignBundle $bundle, string $reason): MarketingDesignBundle
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan arsip wajib diisi.']);
        }

        return DB::transaction(function () use ($actor, $bundle, $reason): MarketingDesignBundle {
            $locked = $this->locks->bundle($bundle->id);
            $this->access->authorize($actor, 'archive', $locked);

            $locked->update([
                'status' => MarketingDesignBundleStatus::Archived,
                'archived_at' => now(),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            $this->audit->log(MarketingDesignAuditEvent::BundleArchived, $locked, $actor, fromStatus: MarketingDesignBundleStatus::Active->value, toStatus: MarketingDesignBundleStatus::Archived->value, reason: $reason);

            return $locked;
        });
    }
}
