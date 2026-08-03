<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BriefingReviewStatus: string implements HasColor, HasLabel
{
    /** Kept temporarily so deployments can read rows before the data migration runs. */
    case LegacyPending = 'pending';
    case SupervisorReview = 'supervisor_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::LegacyPending, self::SupervisorReview => 'Supervisor Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LegacyPending, self::SupervisorReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isAwaitingSupervisorReview(): bool
    {
        return in_array($this, [self::LegacyPending, self::SupervisorReview], true);
    }
}
