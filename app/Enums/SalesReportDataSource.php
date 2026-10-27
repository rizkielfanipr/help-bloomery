<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a Sales Number or Payment Method on a Compliment/Void came from
 * (docs/products/finance/sales-report-compliment-void-remediation/prd.md §9.2, §10.3). Only `EsbSnapshot` is
 * verified against the ESB transactions of the same report, branch, date, and shift.
 */
enum SalesReportDataSource: string implements HasColor, HasLabel
{
    case EsbSnapshot = 'esb_snapshot';
    case Manual = 'manual';
    case Legacy = 'legacy';

    public function getLabel(): string
    {
        return match ($this) {
            self::EsbSnapshot => 'Verified ESB',
            self::Manual => 'Manual (Unverified)',
            self::Legacy => 'Legacy',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EsbSnapshot => 'success',
            self::Manual => 'warning',
            self::Legacy => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $source): array => [$source->value => $source->getLabel()])->all();
    }

    /** @return list<string> */
    public static function submittableValues(): array
    {
        return [self::EsbSnapshot->value, self::Manual->value];
    }
}
