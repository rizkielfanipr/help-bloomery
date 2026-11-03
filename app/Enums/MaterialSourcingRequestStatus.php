<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** docs/products/purchasing/employee-sourcing-request/prd.md §13. */
enum MaterialSourcingRequestStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case RevisionRequested = 'revision_requested';
    case Converted = 'converted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Menunggu Purchasing',
            self::InReview => 'Diproses Purchasing',
            self::RevisionRequested => 'Perlu Perbaikan',
            self::Converted => 'Menjadi Sourcing',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted, self::RevisionRequested => 'warning',
            self::InReview => 'info',
            self::Converted => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Submitted => [self::InReview, self::Cancelled],
            self::InReview => [self::RevisionRequested, self::Converted, self::Rejected],
            self::RevisionRequested => [self::Submitted, self::Cancelled],
            self::Converted, self::Rejected, self::Cancelled => [],
        }, true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Converted, self::Rejected, self::Cancelled], true);
    }

    /** @return list<self> Statuses shown by default and counted by the Purchasing navigation badge. */
    public static function actionable(): array
    {
        return [self::Submitted, self::InReview, self::RevisionRequested];
    }

    /** @return list<string> */
    public static function actionableValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::actionable());
    }
}
