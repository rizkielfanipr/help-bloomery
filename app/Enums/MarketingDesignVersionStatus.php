<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MarketingDesignVersionStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
    case Published = 'published';
    case Superseded = 'superseded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'Menunggu Review',
            self::RevisionRequired => 'Perlu Revisi',
            self::Approved => 'Disetujui',
            self::Published => 'Published',
            self::Superseded => 'Superseded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'info',
            self::RevisionRequired => 'warning',
            self::Approved => 'primary',
            self::Published => 'success',
            self::Superseded => 'gray',
        };
    }

    /** Immutable statuses reject every content change; only the system may move published to superseded. */
    public function isImmutable(): bool
    {
        return in_array($this, [self::Published, self::Superseded], true);
    }

    /** A bundle may hold one open version at a time, so a new revision never competes with unfinished work. */
    public function isOpen(): bool
    {
        return ! $this->isImmutable();
    }
}
