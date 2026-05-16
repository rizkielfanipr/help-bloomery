<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum FormFieldType: string implements HasIcon, HasLabel
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case File = 'file';
    case Date = 'date';
    case Toggle = 'toggle';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::Textarea => 'Teks Panjang',
            self::Number => 'Angka',
            self::Select => 'Pilihan (Dropdown)',
            self::File => 'Upload File',
            self::Date => 'Tanggal',
            self::Toggle => 'Ya / Tidak',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Text => 'heroicon-o-bars-2',
            self::Textarea => 'heroicon-o-document-text',
            self::Number => 'heroicon-o-hashtag',
            self::Select => 'heroicon-o-chevron-up-down',
            self::File => 'heroicon-o-paper-clip',
            self::Date => 'heroicon-o-calendar',
            self::Toggle => 'heroicon-o-check-circle',
        };
    }
}
