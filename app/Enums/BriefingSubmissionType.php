<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BriefingSubmissionType: string implements HasColor, HasLabel
{
    case CameraOnly = 'camera_only';
    case Photo = 'photo';
    case PhotoAndText = 'photo_and_text';
    case TextOnly = 'text_only';
    case Checkbox = 'checkbox';

    public function getLabel(): string
    {
        return match ($this) {
            self::CameraOnly => 'Foto Kamera',
            self::Photo => 'Foto Bebas',
            self::PhotoAndText => 'Foto + Teks',
            self::TextOnly => 'Teks Saja',
            self::Checkbox => 'Centang Selesai',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CameraOnly => 'info',
            self::Photo => 'primary',
            self::PhotoAndText => 'warning',
            self::TextOnly => 'success',
            self::Checkbox => 'gray',
        };
    }

    public function requiresPhoto(): bool
    {
        return in_array($this, [self::CameraOnly, self::Photo, self::PhotoAndText]);
    }

    public function isCameraOnly(): bool
    {
        return $this === self::CameraOnly;
    }

    public function requiresText(): bool
    {
        return in_array($this, [self::TextOnly, self::PhotoAndText]);
    }

    public function isTextOnly(): bool
    {
        return $this === self::TextOnly;
    }
}
