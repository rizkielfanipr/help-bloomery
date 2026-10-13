<?php

namespace App\Actions\Briefing;

use App\Models\BriefingRecord;

/**
 * docs/daily-briefing-bundle-approval-prd.md §9: outcome of a bundle approve/reject/reopen. `changed`
 * is false when a repeated request found the decision already applied, so callers can tell an
 * idempotent no-op apart from a fresh transition.
 */
final readonly class BriefingBundleReviewResult
{
    private function __construct(
        public bool $successful,
        public string $reason,
        public string $message,
        public ?BriefingRecord $bundle = null,
        public bool $changed = false,
    ) {}

    public static function applied(BriefingRecord $bundle, string $message): self
    {
        return new self(true, 'ok', $message, $bundle, true);
    }

    public static function alreadyApplied(BriefingRecord $bundle, string $message): self
    {
        return new self(true, 'already_applied', $message, $bundle, false);
    }

    public static function denied(string $reason, string $message): self
    {
        return new self(false, $reason, $message);
    }
}
