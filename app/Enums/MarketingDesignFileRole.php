<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Format and size contract per file role (docs/products/brand-marketing/design-master-library/prd.md §18.2).
 */
enum MarketingDesignFileRole: string implements HasColor, HasLabel
{
    case Preview = 'preview';
    case Production = 'production';
    case Source = 'source';
    case Supporting = 'supporting';

    /** Matches the global Livewire temporary upload rule; raising it needs the infrastructure audit in PRD §18.2. */
    public const MAX_KILOBYTES = 12288;

    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::Preview => 'Preview',
            self::Production => 'Production',
            self::Source => 'Source',
            self::Supporting => 'Supporting',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Preview => 'info',
            self::Production => 'success',
            self::Source => 'warning',
            self::Supporting => 'gray',
        };
    }

    /** @return list<string> */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Preview => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            self::Production => ['application/pdf', 'application/zip'],
            self::Source => ['application/zip'],
            self::Supporting => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'application/zip'],
        };
    }

    /** @return list<string> */
    public function extensions(): array
    {
        return match ($this) {
            self::Preview => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            self::Production => ['pdf', 'zip'],
            self::Source => ['zip'],
            self::Supporting => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'zip'],
        };
    }

    /**
     * Browsers on Windows report ZIP files as application/x-zip-compressed, so the upload field also accepts it;
     * the server still validates the MIME detected from the content.
     *
     * @return list<string>
     */
    public function acceptedBrowserTypes(): array
    {
        return in_array('application/zip', $this->mimeTypes(), true)
            ? [...$this->mimeTypes(), 'application/x-zip-compressed']
            : $this->mimeTypes();
    }

    public function formatHint(): string
    {
        return 'Format '.strtoupper(implode(', ', $this->extensions())).'. Maksimal '.(self::MAX_KILOBYTES / 1024).' MB per file.';
    }

    public static function extensionForMime(string $mimeType): ?string
    {
        return self::EXTENSION_BY_MIME[$mimeType] ?? null;
    }
}
