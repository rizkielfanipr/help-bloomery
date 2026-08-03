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
            self::New => 'New Purchase',
            self::Broken => 'Replacement (Broken)',
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
