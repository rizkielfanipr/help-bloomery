<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** docs/customer-complaints-prd.md §8.2. */
enum CustomerComplaintCategory: string implements HasLabel
{
    case ProductQuality = 'product_quality';
    case Service = 'service';
    case OrderAccuracy = 'order_accuracy';
    case Delivery = 'delivery';
    case CleanlinessFacility = 'cleanliness_facility';
    case Payment = 'payment';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::ProductQuality => 'Product Quality',
            self::Service => 'Service',
            self::OrderAccuracy => 'Order Accuracy',
            self::Delivery => 'Delivery',
            self::CleanlinessFacility => 'Cleanliness & Facility',
            self::Payment => 'Payment',
            self::Other => 'Other',
        };
    }
}
