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
}
