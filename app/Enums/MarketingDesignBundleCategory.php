<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MarketingDesignBundleCategory: string implements HasLabel
{
    case Regular = 'regular';
    case Seasonal = 'seasonal';
    case Campaign = 'campaign';
    case ProductLaunch = 'product_launch';
    case StoreOpening = 'store_opening';
    case Partnership = 'partnership';
    case Event = 'event';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Regular => 'Reguler',
            self::Seasonal => 'Seasonal',
            self::Campaign => 'Campaign',
            self::ProductLaunch => 'Product Launch',
            self::StoreOpening => 'Store Opening',
            self::Partnership => 'Partnership',
            self::Event => 'Event',
            self::Other => 'Lainnya',
        };
    }
}
