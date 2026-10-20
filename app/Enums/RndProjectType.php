<?php

namespace App\Enums;

enum RndProjectType: string
{
    case ReleaseProduct = 'release_product';
    case Premix = 'premix';
    case Partnership = 'partnership';

    public function label(): string
    {
        return match ($this) {
            self::ReleaseProduct => 'Release Product',
            self::Premix => 'Premix',
            self::Partnership => 'Partnership',
        };
    }
}
