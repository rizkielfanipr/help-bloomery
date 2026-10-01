<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * docs/customer-complaints-prd.md §11. Normal transition is New -> InReview -> Resolved ->
 * Closed; reopening a case is out of scope for this version.
 */
enum CustomerComplaintStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InReview => 'In Review',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InReview => 'warning',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    /** Resolved and Closed both require a resolution to already be recorded (§11). */
    public function requiresResolution(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }

    /**
     * Only the normal forward transition is allowed in this version; reopening a case is
     * explicitly out of scope (§11 "Pembukaan kembali kasus tidak termasuk versi pertama").
     */
    public function canTransitionTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        return match ($this) {
            self::New => $next === self::InReview,
            self::InReview => $next === self::Resolved,
            self::Resolved => $next === self::Closed,
            self::Closed => false,
        };
    }
}
