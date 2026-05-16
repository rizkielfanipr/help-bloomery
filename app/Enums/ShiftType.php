<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ShiftType: string implements HasColor, HasLabel
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case FullDay = 'full_day';

    public function getLabel(): string
    {
        return match ($this) {
            self::Morning => 'Pagi',
            self::Afternoon => 'Siang',
            self::FullDay => 'Full Day',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Morning => 'info',
            self::Afternoon => 'warning',
            self::FullDay => 'primary',
        };
    }
}
