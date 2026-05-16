<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DesignCategory: string implements HasLabel
{
    case SocialMedia = 'social_media';
    case Banner = 'banner';
    case Poster = 'poster';
    case Packaging = 'packaging';
    case Logo = 'logo';
    case Brosur = 'brosur';
    case Presentasi = 'presentasi';

    public function getLabel(): string
    {
        return match ($this) {
            self::SocialMedia => 'Social Media',
            self::Banner => 'Banner',
            self::Poster => 'Poster',
            self::Packaging => 'Packaging',
            self::Logo => 'Logo',
            self::Brosur => 'Brosur',
            self::Presentasi => 'Presentasi',
        };
    }
}
