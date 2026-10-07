<?php

namespace App\Enums;

use App\Models\RndProductEsbShelfLife;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Data status of a WIP's Shelf Life master (docs/rnd-wip-shelf-life-prd.md §16.1, §17.3). Shared by
 * BOM Adjustment, the Project WIP section, and the Ready/Released gate so all three agree.
 */
enum RndWipShelfLifeStatus: string implements HasColor, HasLabel
{
    case Complete = 'complete';
    case Missing = 'missing';
    case Inactive = 'inactive';
    case IdentityIncomplete = 'identity_incomplete';

    public static function for(?int $productDetailId, ?RndProductEsbShelfLife $master): self
    {
        return match (true) {
            $productDetailId === null || $productDetailId < 1 => self::IdentityIncomplete,
            $master === null => self::Missing,
            ! $master->is_active => self::Inactive,
            default => self::Complete,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Complete => 'Lengkap',
            self::Missing => 'Belum Diisi',
            self::Inactive => 'Tidak Aktif',
            self::IdentityIncomplete => 'Identitas Tidak Lengkap',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Complete => 'success',
            self::Missing => 'warning',
            self::Inactive => 'gray',
            self::IdentityIncomplete => 'danger',
        };
    }

    public function isComplete(): bool
    {
        return $this === self::Complete;
    }
}
