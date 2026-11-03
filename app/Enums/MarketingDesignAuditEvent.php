<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MarketingDesignAuditEvent: string implements HasLabel
{
    case BundleCreated = 'bundle_created';
    case BundleUpdated = 'bundle_updated';
    case BundleArchived = 'bundle_archived';
    case BundleRestored = 'bundle_restored';
    case ItemAdded = 'item_added';
    case ItemUpdated = 'item_updated';
    case ItemSpecificationUpdated = 'item_specification_updated';
    case ItemDeleted = 'item_deleted';
    case FileReplaced = 'file_replaced';
    case FileDownloaded = 'file_downloaded';

    public function getLabel(): string
    {
        return match ($this) {
            self::BundleCreated => 'Bundle dibuat',
            self::BundleUpdated => 'Bundle diperbarui',
            self::BundleArchived => 'Bundle diarsipkan',
            self::BundleRestored => 'Bundle dipulihkan',
            self::ItemAdded => 'Item ditambahkan',
            self::ItemUpdated => 'Item diperbarui',
            self::ItemSpecificationUpdated => 'Spesifikasi produksi diperbarui',
            self::ItemDeleted => 'Item dihapus permanen',
            self::FileReplaced => 'File desain diganti',
            self::FileDownloaded => 'File desain diunduh',
        };
    }

    public function isDownload(): bool
    {
        return $this === self::FileDownloaded;
    }
}
