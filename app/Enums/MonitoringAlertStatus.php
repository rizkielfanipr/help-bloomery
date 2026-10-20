<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * One deduplicated alert is Active until its source recovers (docs/system-monitoring-prd.md §17).
 */
enum MonitoringAlertStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Resolved => 'Resolved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'danger',
            self::Resolved => 'success',
        };
    }
}
