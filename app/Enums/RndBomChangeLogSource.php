<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a `rnd_bom_change_logs` record originated from (docs/rnd-bom-adjustment-prd.md §15, §16).
 */
enum RndBomChangeLogSource: string implements HasColor, HasLabel
{
    case BomAdjustment = 'bom_adjustment';
    case Project = 'project';
    case ExternalEsb = 'external_esb';

    public function getLabel(): string
    {
        return match ($this) {
            self::BomAdjustment => 'Recipe Adjustment',
            self::Project => 'R&D Project',
            self::ExternalEsb => 'External ESB',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::BomAdjustment => 'info',
            self::Project => 'gray',
            self::ExternalEsb => 'warning',
        };
    }
}
