<?php

namespace App\Services;

use App\Enums\BriefingReviewStatus;
use App\Models\BriefingItem;
use Illuminate\Support\Collection;

/**
 * docs/daily-briefing-remediation-prd.md §17.2/§17.3 (Phase 12). Classifies existing rejected
 * items into three buckets so cleanup never touches a real submission. This never mutates
 * anything — both `briefing:audit-invalid-records` and `briefing:cleanup-invalid-records
 * --dry-run` only read through this classifier.
 *
 * `deadline_reject_reason` no longer exists (Phase 7 dropped it — the scheduler never creates
 * this kind of row going forward), so the "reason text matches the configured deadline message"
 * criterion from §17.2 is replaced by the rest of that list, which is sufficient on its own: a
 * rejected item nobody ever actually touched (not completed, no photo, no note) and that was
 * rejected by the system (reviewed_by null) rather than a reviewer.
 */
class BriefingCleanupClassifier
{
    public const SAFE_CLEANUP_CANDIDATE = 'safe_cleanup_candidate';

    public const MANUAL_REVIEW = 'manual_review';

    public const VALID_SUBMISSION = 'valid_submission';

    public function classify(BriefingItem $item): string
    {
        if ($item->review_status !== BriefingReviewStatus::Rejected) {
            return self::VALID_SUBMISSION;
        }

        if ($item->reviewed_by !== null) {
            // A human reviewer rejected this — always a real, valid submission.
            return self::VALID_SUBMISSION;
        }

        $neverFilledIn = ! $item->is_completed
            && $item->completed_at === null
            && empty($item->photo_paths)
            && (blank($item->notes));

        return $neverFilledIn ? self::SAFE_CLEANUP_CANDIDATE : self::MANUAL_REVIEW;
    }

    /**
     * @param  Collection<int, BriefingItem>  $items
     * @return array<string, Collection<int, BriefingItem>>
     */
    public function classifyMany(Collection $items): array
    {
        $defaults = [
            self::SAFE_CLEANUP_CANDIDATE => collect(),
            self::MANUAL_REVIEW => collect(),
            self::VALID_SUBMISSION => collect(),
        ];

        return array_merge($defaults, $items->groupBy(fn (BriefingItem $item): string => $this->classify($item))->all());
    }
}
