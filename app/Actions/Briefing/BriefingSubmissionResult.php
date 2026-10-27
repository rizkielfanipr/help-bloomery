<?php

namespace App\Actions\Briefing;

use App\Models\BriefingItem;

/**
 * docs/products/daily-briefing/remediation/prd.md §20 (Phase 5): a result the UI can translate directly
 * into a notification, instead of the action throwing for expected business outcomes.
 */
final readonly class BriefingSubmissionResult
{
    private function __construct(
        public bool $successful,
        public string $reason,
        public string $message,
        public ?BriefingItem $item = null,
    ) {}

    public static function success(BriefingItem $item): self
    {
        return new self(true, 'saved', 'Data akan diverifikasi oleh HR.', $item);
    }

    public static function denied(string $reason, string $message): self
    {
        return new self(false, $reason, $message);
    }
}
