<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MarketingMaterialFulfillmentStatus: string implements HasColor, HasLabel
{
    case NotStarted = 'not_started';
    case Ordered = 'ordered';
    case Received = 'received';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotStarted => 'Belum Diproses',
            self::Ordered => 'Sudah Dipesan',
            self::Received => 'Sudah Diterima',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::Ordered => 'warning',
            self::Received => 'success',
        };
    }
}
