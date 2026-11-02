<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockCardStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingSupervisor = 'pending_supervisor';
    case PendingFinance = 'pending_finance';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSupervisor => 'Supervisor Review',
            self::PendingFinance => 'Finance Review',
            self::Completed => 'Completed',
        };
    }

    /** Wording for store staff on the Employee App. */
    public function getStaffLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSupervisor => 'Menunggu review Supervisor',
            self::PendingFinance => 'Menunggu review Finance',
            self::Completed => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingSupervisor, self::PendingFinance => 'warning',
            self::Completed => 'success',
        };
    }

    /** Staff can only submit once — after that, corrections happen in the back office. */
    public function canBeEditedBySubmitter(): bool
    {
        return $this === self::Draft;
    }
}
