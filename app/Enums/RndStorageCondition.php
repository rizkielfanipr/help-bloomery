<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Storage condition of the WIP Shelf Life master (docs/rnd-wip-shelf-life-prd.md §12.5).
 * Temperature details such as `2–5°C` belong in the master's notes, not in new cases.
 */
enum RndStorageCondition: string implements HasLabel
{
    case Dry = 'dry';
    case Chiller = 'chiller';
    case Frozen = 'frozen';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dry => 'Dry',
            self::Chiller => 'Chiller',
            self::Frozen => 'Frozen',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $condition): array => [$condition->value => $condition->getLabel()])->all();
    }
}
