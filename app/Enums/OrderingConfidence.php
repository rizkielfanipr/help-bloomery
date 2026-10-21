<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderingConfidence: string implements HasColor, HasLabel
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Blocked = 'blocked';

    public function getLabel(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
            self::Blocked => 'Blocked',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::High => 'success',
            self::Medium => 'info',
            self::Low => 'warning',
            self::Blocked => 'danger',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
            self::Blocked => 0,
        };
    }

    public static function fromRank(int $rank): self
    {
        return match (true) {
            $rank >= 3 => self::High,
            $rank === 2 => self::Medium,
            $rank === 1 => self::Low,
            default => self::Blocked,
        };
    }
}
