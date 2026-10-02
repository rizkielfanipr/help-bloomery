<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * docs/store-sales-order-prd.md §10.3, §10.5, Phase 0 decision #6: a centralized enum rather than
 * a master data table, per the PRD's explicit "Pilihan disimpan sebagai enum/config terpusat".
 */
enum StoreSalesOrderProductType: string implements HasLabel
{
    case Db50Pack = 'db50_pack';
    case Db100Pack = 'db100_pack';
    case SnackBox = 'snack_box';
    case BigBox = 'big_box';
    case Tampah1 = 'tampah_1';
    case Tampah2 = 'tampah_2';
    case DessertCupWeddingCake = 'dessert_cup_wedding_cake';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Db50Pack => 'DB50 Pack',
            self::Db100Pack => 'DB100 Pack',
            self::SnackBox => 'Snack Box',
            self::BigBox => 'Big Box',
            self::Tampah1 => 'Tampah 1',
            self::Tampah2 => 'Tampah 2',
            self::DessertCupWeddingCake => 'Dessert Cup Wedding Cake',
            self::Custom => 'Custom',
        };
    }

    public function requiresCustomDetail(): bool
    {
        return $this === self::Custom;
    }
}
