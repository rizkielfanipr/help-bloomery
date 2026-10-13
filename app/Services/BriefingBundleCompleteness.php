<?php

namespace App\Services;

/**
 * docs/daily-briefing-bundle-approval-prd.md §8.3: how much of a bundle's required snapshot is
 * backed by a valid submission.
 */
final readonly class BriefingBundleCompleteness
{
    /**
     * @param  list<string>  $missingTaskKeys  required points without a valid submission, including ones flagged for correction
     */
    public function __construct(
        public int $required,
        public int $completed,
        public array $missingTaskKeys,
    ) {}

    /**
     * A bundle with no required points is never complete, so an empty snapshot cannot slip into review.
     */
    public function isComplete(): bool
    {
        return $this->required > 0 && $this->missingTaskKeys === [];
    }
}
