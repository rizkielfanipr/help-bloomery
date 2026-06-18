<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BriefingPeriod: string implements HasColor, HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Daily => 'violet',
            self::Weekly => 'blue',
            self::Monthly => 'warning',
        };
    }

    public function recordDate(): string
    {
        return match ($this) {
            self::Daily => now()->toDateString(),
            self::Weekly => now()->startOfWeek()->toDateString(),
            self::Monthly => now()->startOfMonth()->toDateString(),
        };
    }

    public function periodLabel(): string
    {
        return match ($this) {
            self::Daily => 'hari ini',
            self::Weekly => 'minggu ini',
            self::Monthly => 'bulan ini',
        };
    }
}
