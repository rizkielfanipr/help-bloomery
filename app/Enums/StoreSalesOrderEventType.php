<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** docs/store-sales-order-prd.md §10.2. */
enum StoreSalesOrderEventType: string implements HasLabel
{
    case Birthday = 'birthday';
    case Wedding = 'wedding';
    case Corporate = 'corporate';
    case Gathering = 'gathering';
    case PersonalOrder = 'personal_order';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Birthday => 'Birthday',
            self::Wedding => 'Wedding',
            self::Corporate => 'Corporate',
            self::Gathering => 'Gathering',
            self::PersonalOrder => 'Personal Order',
            self::Other => 'Other',
        };
    }
}
