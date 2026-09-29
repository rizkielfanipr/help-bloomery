<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What kind of change a `rnd_bom_change_logs` record represents (docs/rnd-bom-adjustment-prd.md §15-§16).
 */
enum RndBomChangeLogEvent: string implements HasColor, HasLabel
{
    case ComponentUpdated = 'component_updated';
    case ExternalChangeDetected = 'external_change_detected';

    public function getLabel(): string
    {
        return match ($this) {
            self::ComponentUpdated => 'Component Updated',
            self::ExternalChangeDetected => 'External Change Detected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ComponentUpdated => 'info',
            self::ExternalChangeDetected => 'warning',
        };
    }
}
