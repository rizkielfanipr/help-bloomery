<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * docs/daily-briefing-bundle-approval-prd.md §8: the persisted lifecycle of a branch bundle.
 * `not_due`, `missing` and `not_applicable` are derived from assignment + deadline and are never
 * stored here, so they cannot be faked by writing a row.
 */
enum BriefingBundleStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case SupervisorReview = 'supervisor_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reopened = 'reopened';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::SupervisorReview => 'Pending Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Reopened => 'Reopened',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::SupervisorReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Reopened => 'info',
        };
    }

    /**
     * Rejected and approved bundles only change again through an audited reopen (§9.3/§9.4).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::SupervisorReview],
            self::SupervisorReview => [self::Approved, self::Rejected],
            self::Rejected, self::Approved => [self::Reopened],
            self::Reopened => [self::SupervisorReview],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isAwaitingReview(): bool
    {
        return $this === self::SupervisorReview;
    }

    public function acceptsSubmissions(): bool
    {
        return in_array($this, [self::Draft, self::Reopened], true);
    }
}
