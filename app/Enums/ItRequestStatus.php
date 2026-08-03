<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItRequestStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Review = 'in_review';
    case Progress = 'in_progress';
    case Waiting = 'waiting_user';
    case Escalated = 'escalated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Review => 'Review',
            self::Progress => 'In Progress',
            self::Waiting => 'Waiting User',
            self::Escalated => 'Escalated',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Review, self::Waiting => 'warning',
            self::Progress => 'primary',
            self::Escalated, self::Cancelled => 'danger',
            self::Completed => 'success',
        };
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Review],
            self::Review => [self::Progress],
            self::Progress => [self::Waiting, self::Escalated, self::Completed],
            self::Waiting, self::Escalated => [self::Progress],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $status === $this || in_array($status, $this->allowedTransitions(), true);
    }
}
