<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderingStockLocationMode: string implements HasColor, HasLabel
{
    case Unconfigured = 'unconfigured';
    case Selected = 'selected';
    case All = 'all';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unconfigured => 'Belum Dikonfigurasi',
            self::Selected => 'Lokasi Terpilih',
            self::All => 'Semua Lokasi Branch',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unconfigured => 'warning',
            self::Selected => 'success',
            self::All => 'info',
        };
    }
}
