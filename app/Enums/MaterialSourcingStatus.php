<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MaterialSourcingStatus: string implements HasColor, HasLabel
{
    case NotStarted = 'not_started';
    case PendingRndReview = 'pending_rnd_review';
    case PendingFinanceReview = 'pending_finance_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotStarted => 'Belum Sourcing',
            self::PendingRndReview => 'Review RnD',
            self::PendingFinanceReview => 'Review Finance',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::PendingRndReview, self::PendingFinanceReview => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
