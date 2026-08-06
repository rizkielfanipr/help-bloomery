<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContentRequestStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case Approval = 'approval';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::InProgress => 'In Progress',
            self::Approval => 'Approval',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::InProgress => 'info',
            self::Approval => 'warning',
            self::Completed => 'success',
            self::Rejected => 'danger',
        };
    }

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::InProgress],
            self::InProgress => [self::Approval],
            self::Approval => [self::Completed, self::Rejected],
            self::Completed, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
