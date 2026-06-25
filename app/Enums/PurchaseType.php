<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseType: string implements HasColor, HasLabel
{
    case New = 'new';
    case Broken = 'broken';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Broken => 'Rusak / Penggantian',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::New => 'success',
            self::Broken => 'warning',
        };
    }
}
