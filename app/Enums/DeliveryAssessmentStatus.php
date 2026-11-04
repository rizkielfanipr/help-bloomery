<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** docs/products/purchasing/vendor-compliance-late-delivery/prd.md §14.1. Null on a receipt means it predates the feature. */
enum DeliveryAssessmentStatus: string implements HasColor, HasLabel
{
    case OnTime = 'on_time';
    case Late = 'late';
    case NotAssessable = 'not_assessable';
    case NotApplicable = 'not_applicable';

    public function getLabel(): string
    {
        return match ($this) {
            self::OnTime => 'Tepat Waktu',
            self::Late => 'Terlambat',
            self::NotAssessable => 'Tidak Dapat Dinilai',
            self::NotApplicable => 'Tidak Dinilai (PO Susulan)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OnTime => 'success',
            self::Late => 'warning',
            self::NotAssessable, self::NotApplicable => 'gray',
        };
    }
}
