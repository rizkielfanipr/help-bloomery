<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Trend of a product within its branch. `NewlySold` replaces an infinite growth percentage when
 * the comparison window had no demand (docs/products/analytics/ordering-recommendation/prd.md §13.4, §15.3).
 */
enum OrderingTrendLabel: string implements HasLabel
{
    case Rising = 'rising';
    case Falling = 'falling';
    case Stable = 'stable';
    case NewlySold = 'newly_sold';
    case NoDemand = 'no_demand';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rising => 'Trend Naik',
            self::Falling => 'Trend Turun',
            self::Stable => 'Stabil',
            self::NewlySold => 'New/Recently Sold',
            self::NoDemand => 'Tidak ada demand',
            self::Unknown => 'Belum dapat dihitung',
        };
    }
}
