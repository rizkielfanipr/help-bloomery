<?php

namespace App\Services;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingPeriod;
use Carbon\CarbonInterface;

/**
 * docs/daily-briefing-bundle-approval-prd.md §15.3: the classification of one legacy group
 * (branch + period + normalized period_start) and, when it can be converted automatically, exactly
 * what the bundle will look like. Built by BriefingLegacyGroupingClassifier; never invents a
 * reviewer or a timestamp — every value comes from the legacy rows.
 */
final readonly class BriefingLegacyGroupingPlan
{
    public const APPROVED = 'approved';

    public const SUPERVISOR_REVIEW = 'supervisor_review';

    public const REJECTED = 'rejected';

    public const DRAFT = 'draft';

    public const MISSING = 'missing';

    public const CLEANUP_CANDIDATE = 'cleanup_candidate';

    public const MANUAL_REVIEW = 'manual_review';

    public const MUTATING = [self::APPROVED, self::SUPERVISOR_REVIEW, self::REJECTED, self::DRAFT];

    /**
     * @param  list<int>  $sourceRecordIds
     * @param  list<int>  $checkboxBackfillItemIds  legacy checkbox items saved without completion fields
     * @param  array<int, string>  $corrections  item id => correction note, for a rejected bundle
     */
    public function __construct(
        public string $classification,
        public ?int $branchId,
        public BriefingPeriod $period,
        public ?CarbonInterface $periodStart,
        public array $sourceRecordIds,
        public ?string $reason = null,
        public ?int $canonicalRecordId = null,
        public ?BriefingBundleStatus $status = null,
        public ?CarbonInterface $completedAt = null,
        public ?int $reviewedBy = null,
        public ?CarbonInterface $reviewedAt = null,
        public ?string $reviewNotes = null,
        public array $checkboxBackfillItemIds = [],
        public array $corrections = [],
        public array $leftInSourceItemIds = [],
        public array $missingTaskKeys = [],
    ) {}

    public function isMutating(): bool
    {
        return in_array($this->classification, self::MUTATING, true);
    }

    public function key(): string
    {
        return ($this->branchId ?? 'none').'|'.$this->period->value.'|'.($this->periodStart?->toDateString() ?? 'none');
    }
}
