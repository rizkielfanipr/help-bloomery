<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** docs/customer-complaints-prd.md §8.1. */
enum CustomerComplaintSource: string implements HasLabel
{
    case InStore = 'in_store';
    case WhatsApp = 'whatsapp';
    case Phone = 'phone';
    case Delivery = 'delivery';
    case Marketplace = 'marketplace';
    case SocialMedia = 'social_media';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::InStore => 'In Store',
            self::WhatsApp => 'WhatsApp',
            self::Phone => 'Phone',
            self::Delivery => 'Delivery',
            self::Marketplace => 'Marketplace',
            self::SocialMedia => 'Social Media',
            self::Other => 'Other',
        };
    }
}
