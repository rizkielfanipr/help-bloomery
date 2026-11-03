<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** docs/products/purchasing/vendor-compliance-late-delivery/prd.md §13.1. */
enum VendorComplianceLiabilityStatus: string implements HasColor, HasLabel
{
    case NotApplicable = 'not_applicable';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Waived = 'waived';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotApplicable => 'Tidak Berlaku',
            self::Pending => 'Menunggu Verifikasi',
            self::Confirmed => 'Pelanggaran Dikonfirmasi',
            self::Waived => 'Dikecualikan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotApplicable => 'gray',
            self::Pending => 'warning',
            self::Confirmed => 'danger',
            self::Waived => 'success',
        };
    }

    public function isDecided(): bool
    {
        return in_array($this, [self::Confirmed, self::Waived], true);
    }
}
