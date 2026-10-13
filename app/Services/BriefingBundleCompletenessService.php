<?php

namespace App\Services;

use App\Enums\BriefingSubmissionType;
use App\Models\BriefingItem;
use App\Models\BriefingRecord;
use App\Models\BriefingRecordRequirement;

/**
 * docs/daily-briefing-bundle-approval-prd.md §8.3: evaluates a bundle against the requirement
 * snapshot it was created with — never against the live task master, so later task edits cannot
 * change whether a past bundle was complete.
 */
class BriefingBundleCompletenessService
{
    public function evaluate(BriefingRecord $bundle): BriefingBundleCompleteness
    {
        $bundle->loadMissing(['requirements', 'items']);

        $itemsByKey = $bundle->items->keyBy('task_key');
        $required = $bundle->requirements->filter(fn (BriefingRecordRequirement $requirement): bool => $requirement->is_required);

        $missing = $required
            ->reject(fn (BriefingRecordRequirement $requirement): bool => $this->itemSatisfies($requirement->submission_type, $itemsByKey->get($requirement->task_key)))
            ->pluck('task_key')
            ->values()
            ->all();

        return new BriefingBundleCompleteness(
            required: $required->count(),
            completed: $required->count() - count($missing),
            missingTaskKeys: $missing,
        );
    }

    /**
     * A point counts once it carries the evidence its submission type needs, is marked completed
     * with a timestamp (checkbox included), and is not waiting on a supervisor-requested correction.
     */
    public function itemSatisfies(BriefingSubmissionType $type, ?BriefingItem $item): bool
    {
        if ($item === null || ! $item->is_completed || $item->completed_at === null || $item->requires_correction) {
            return false;
        }

        if ($type->requiresPhoto() && empty($item->photo_paths)) {
            return false;
        }

        if ($type->requiresText() && blank($item->notes)) {
            return false;
        }

        return true;
    }
}
