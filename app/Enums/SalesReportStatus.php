<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SalesReportStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingSupervisor = 'pending_supervisor';
    case PendingFinance = 'pending_finance';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case RejectedBySystem = 'rejected_by_system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSupervisor => 'Supervisor Review',
            self::PendingFinance => 'Finance Review',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::RejectedBySystem => 'Rejected by System',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingSupervisor, self::PendingFinance => 'warning',
            self::Completed => 'success',
            self::Rejected, self::RejectedBySystem => 'danger',
        };
    }

    public function canBeEditedBySubmitter(): bool
    {
        return $this === self::Draft;
    }
}
