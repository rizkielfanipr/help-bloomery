<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MarketingDesignAuditEvent: string implements HasLabel
{
    case BundleCreated = 'bundle_created';
    case BundleUpdated = 'bundle_updated';
    case BundleArchived = 'bundle_archived';
    case BundleRestored = 'bundle_restored';
    case VersionCreated = 'version_created';
    case VersionUpdated = 'version_updated';
    case VersionSubmitted = 'version_submitted';
    case VersionRevisionRequired = 'version_revision_required';
    case VersionReopened = 'version_reopened';
    case VersionApproved = 'version_approved';
    case VersionPublished = 'version_published';
    case VersionSuperseded = 'version_superseded';
    case ItemAdded = 'item_added';
    case ItemUpdated = 'item_updated';
    case ItemRemoved = 'item_removed';
    case FileUploaded = 'file_uploaded';
    case FileReplaced = 'file_replaced';
    case FileRemoved = 'file_removed';
    case ProductionFileDownloaded = 'production_file_downloaded';
    case SourceFileDownloaded = 'source_file_downloaded';

    public function getLabel(): string
    {
        return match ($this) {
            self::BundleCreated => 'Bundle dibuat',
            self::BundleUpdated => 'Bundle diperbarui',
            self::BundleArchived => 'Bundle diarsipkan',
            self::BundleRestored => 'Bundle dipulihkan',
            self::VersionCreated => 'Version dibuat',
            self::VersionUpdated => 'Ringkasan version diperbarui',
            self::VersionSubmitted => 'Dikirim untuk review',
            self::VersionRevisionRequired => 'Dikembalikan untuk revisi',
            self::VersionReopened => 'Dibuka kembali sebagai draft',
            self::VersionApproved => 'Disetujui',
            self::VersionPublished => 'Dipublikasikan',
            self::VersionSuperseded => 'Digantikan versi baru',
            self::ItemAdded => 'Item ditambahkan',
            self::ItemUpdated => 'Item diperbarui',
            self::ItemRemoved => 'Item dilepas',
            self::FileUploaded => 'File diunggah',
            self::FileReplaced => 'File diganti',
            self::FileRemoved => 'File dilepas',
            self::ProductionFileDownloaded => 'File production diunduh',
            self::SourceFileDownloaded => 'File source diunduh',
        };
    }

    public function isDownload(): bool
    {
        return in_array($this, [self::ProductionFileDownloaded, self::SourceFileDownloaded], true);
    }
}
