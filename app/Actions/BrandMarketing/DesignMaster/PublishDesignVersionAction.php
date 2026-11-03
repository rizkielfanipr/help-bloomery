<?php

namespace App\Actions\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignAuditEvent;
use App\Enums\MarketingDesignVersionStatus;
use App\Models\MarketingDesignBundleVersion;
use App\Models\User;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAccess;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignAuditLogger;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignLocks;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignNotifier;
use App\Services\BrandMarketing\DesignMaster\MarketingDesignReadiness;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes an approved version and supersedes the previous one in the same transaction (PRD §11.7).
 * The bundle row lock serializes publishers; the unique published marker is the database backstop.
 * The previous version must release its marker first because the index rejects two published rows.
 */
class PublishDesignVersionAction
{
    public function __construct(
        private readonly MarketingDesignAccess $access,
        private readonly MarketingDesignLocks $locks,
        private readonly MarketingDesignReadiness $readiness,
        private readonly MarketingDesignAuditLogger $audit,
        private readonly MarketingDesignNotifier $notifier,
    ) {}

    /** @throws ValidationException */
    public function execute(User $actor, MarketingDesignBundleVersion $version, ?string $note = null): MarketingDesignBundleVersion
    {
        try {
            $published = DB::transaction(function () use ($actor, $version, $note): MarketingDesignBundleVersion {
                $locked = $this->locks->version($version->marketing_design_bundle_id, $version->id);
                $this->access->authorize($actor, 'publish', $locked);
                $this->readiness->ensureReady($locked);

                $previous = MarketingDesignBundleVersion::query()
                    ->where('marketing_design_bundle_id', $locked->marketing_design_bundle_id)
                    ->where('status', MarketingDesignVersionStatus::Published)
                    ->lockForUpdate()
                    ->first();

                if ($previous !== null) {
                    $previous->update(['status' => MarketingDesignVersionStatus::Superseded, 'published_marker' => null, 'superseded_at' => now()]);
                    $this->audit->log(MarketingDesignAuditEvent::VersionSuperseded, $locked->bundle, $actor, $previous, fromStatus: MarketingDesignVersionStatus::Published->value, toStatus: MarketingDesignVersionStatus::Superseded->value, metadata: ['superseded_by_version_id' => $locked->id]);
                }

                $from = $locked->status;
                $note = filled($note) ? trim((string) $note) : null;
                $locked->update([
                    'status' => MarketingDesignVersionStatus::Published,
                    'published_marker' => 1,
                    'published_by' => $actor->id,
                    'published_at' => now(),
                    'publish_note' => $note,
                ]);
                $this->audit->log(MarketingDesignAuditEvent::VersionPublished, $locked->bundle, $actor, $locked, fromStatus: $from->value, toStatus: $locked->status->value, reason: $note);

                return $locked;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['version' => 'Version lain baru saja dipublikasikan. Muat ulang halaman.']);
        }

        $this->notifier->published($published, $actor);

        return $published;
    }
}
