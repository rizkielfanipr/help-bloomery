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
            self::PendingSupervisor => 'Menunggu Approval SPV',
            self::RejectedBySupervisor => 'Ditolak SPV',
            self::PendingFinance => 'Menunggu Approval Finance',
            self::RejectedByFinance => 'Ditolak Finance',
            self::Completed => 'Selesai',
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
        return in_array($this, [self::Draft, self::RejectedBySupervisor, self::RejectedByFinance], true);
    }
}
