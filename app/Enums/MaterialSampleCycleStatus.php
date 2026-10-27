<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * One sample hand-over → receipt → evaluation cycle (§16). A resample closes the cycle and opens
 * the next one; cycles are never overwritten.
 */
enum MaterialSampleCycleStatus: string implements HasColor, HasLabel
{
    case Prepared = 'prepared';
    case HandedOver = 'handed_over';
    case Received = 'received';
    case Approved = 'approved';
    case ResampleRequested = 'resample_requested';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prepared => 'Awaiting Handover',
            self::HandedOver => 'Handed Over',
            self::Received => 'Received by R&D',
            self::Approved => 'Approved by R&D',
            self::ResampleRequested => 'Resample Requested',
            self::Rejected => 'Rejected by R&D',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prepared => 'gray',
            self::HandedOver, self::Received => 'info',
            self::Approved => 'success',
            self::ResampleRequested => 'warning',
            self::Rejected => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Prepared, self::HandedOver, self::Received], true);
    }
}
