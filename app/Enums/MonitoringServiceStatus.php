<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Health state of one monitored service (docs/operations/monitoring/prd.md §9.1).
 */
enum MonitoringServiceStatus: string implements HasColor, HasLabel
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';
    case Maintenance = 'maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Degraded => 'Degraded',
            self::Down => 'Down',
            self::Unknown => 'Unknown',
            self::Maintenance => 'Maintenance',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Degraded => 'warning',
            self::Down => 'danger',
            self::Unknown, self::Maintenance => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Healthy => 'heroicon-o-check-circle',
            self::Degraded => 'heroicon-o-exclamation-triangle',
            self::Down => 'heroicon-o-x-circle',
            self::Unknown => 'heroicon-o-question-mark-circle',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
        };
    }

    public function isProblem(): bool
    {
        return in_array($this, [self::Degraded, self::Down], true);
    }
}
