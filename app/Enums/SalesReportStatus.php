<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SalesReportStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingSupervisor = 'pending_supervisor';
    case RejectedBySupervisor = 'rejected_by_supervisor';
    case PendingFinance = 'pending_finance';
    case RejectedByFinance = 'rejected_by_finance';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSupervisor => 'Supervisor Review',
            self::RejectedBySupervisor => 'Rejected by Supervisor',
            self::PendingFinance => 'Finance Review',
            self::RejectedByFinance => 'Rejected by Finance',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingSupervisor, self::PendingFinance => 'warning',
            self::RejectedBySupervisor, self::RejectedByFinance => 'danger',
            self::Completed => 'success',
        };
    }

    public function canBeEditedBySubmitter(): bool
    {
        return $this === self::Draft;
    }
}
