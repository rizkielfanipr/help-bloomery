<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItRequestStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Review = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Progress = 'in_progress';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Review => 'Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Progress => 'In Progress',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Review => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Progress => 'primary',
            self::Completed => 'success',
        };
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Review],
            self::Review => [self::Approved, self::Rejected],
            self::Approved => [self::Progress],
            self::Progress => [self::Completed],
            self::Rejected, self::Completed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $status === $this || in_array($status, $this->allowedTransitions(), true);
    }
}
