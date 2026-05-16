<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ErpModule: string implements HasLabel
{
    case Finance = 'finance';
    case Inventory = 'inventory';
    case Sales = 'sales';
    case Production = 'production';
    case Purchasing = 'purchasing';
    case Hr = 'hr';
    case Distribution = 'distribution';

    public function getLabel(): string
    {
        return match ($this) {
            self::Finance => 'Keuangan',
            self::Inventory => 'Inventori',
            self::Sales => 'Penjualan',
            self::Production => 'Produksi',
            self::Purchasing => 'Pembelian',
            self::Hr => 'HR',
            self::Distribution => 'Distribusi',
        };
    }
}
