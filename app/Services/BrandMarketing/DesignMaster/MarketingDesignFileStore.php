<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Enums\MarketingDesignFileRole;
use App\Models\MarketingDesignFile;
use App\Models\MarketingDesignItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Private object storage of design files (PRD §18). Validates MIME from the content and the size,
 * builds the path from IDs only, and never overwrites an object. `discard()` removes an object
 * written for a record that could not be saved. No other code path deletes objects.
 */
class MarketingDesignFileStore
{
    public const DISK = 'b2';

    private const TEMPORARY_URL_MINUTES = 15;

    /**
     * @return array{disk: string, path: string, original_name: string, mime_type: string, extension: string, size_bytes: int, checksum_sha256: string}
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file, MarketingDesignItem $item, MarketingDesignFileRole $role): array
    {
        $this->validate($file, $role);

        $mimeType = (string) $file->getMimeType();
        $extension = (string) MarketingDesignFileRole::extensionForMime($mimeType);
        $checksum = hash_file('sha256', (string) $file->getRealPath());
        $name = Str::uuid().'.'.$extension;
        $directory = $this->directory($item, $role);

        try {
            $path = Storage::disk(self::DISK)->putFileAs($directory, $file, $name);
        } catch (Throwable $exception) {
            Log::warning('Master Design upload failed', ['item_id' => $item->id, 'role' => $role->value, 'exception' => $exception::class]);
            $path = false;
        }

        if (! is_string($path) || $checksum === false) {
            throw ValidationException::withMessages(['file' => 'File gagal disimpan ke penyimpanan. Silakan coba lagi.']);
        }

        return [
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
            'checksum_sha256' => $checksum,
        ];
    }

    /** Compensation for an object whose record was never committed, so no reference can exist. */
    public function discard(string $path): void
    {
        try {
            Storage::disk(self::DISK)->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Master Design upload cleanup failed', ['exception' => $exception::class]);
        }
    }

    public function exists(MarketingDesignFile $file): bool
    {
        return Storage::disk($file->disk)->exists($file->path);
    }

    /** The URL is short-lived and is returned to the caller only: it is never stored or logged. */
    public function temporaryUrl(MarketingDesignFile $file, bool $inline): string
    {
        $disposition = $inline ? 'inline' : 'attachment';

        return Storage::disk($file->disk)->temporaryUrl($file->path, now()->addMinutes(self::TEMPORARY_URL_MINUTES), [
            'ResponseContentDisposition' => $disposition.'; filename="'.$file->safeDownloadName().'"',
            'ResponseContentType' => $file->mime_type,
        ]);
    }

    private function directory(MarketingDesignItem $item, MarketingDesignFileRole $role): string
    {
        $version = $item->version;

        return sprintf(
            'brand-marketing/design-master/brand-%d/bundle-%d/version-%d/item-%d/%s',
            $version->bundle->brand_id,
            $version->marketing_design_bundle_id,
            $version->id,
            $item->id,
            $role->value,
        );
    }

    /** @throws ValidationException */
    private function validate(UploadedFile $file, MarketingDesignFileRole $role): void
    {
        Validator::make(['file' => $file], [
            'file' => [
                'required',
                'file',
                'extensions:'.implode(',', $role->extensions()),
                'mimetypes:'.implode(',', $role->mimeTypes()),
                'max:'.MarketingDesignFileRole::MAX_KILOBYTES,
            ],
        ], [
            'file.extensions' => $role->getLabel().' ditolak. '.$role->formatHint(),
            'file.mimetypes' => $role->getLabel().' ditolak. '.$role->formatHint(),
            'file.max' => 'Ukuran file melebihi batas. '.$role->formatHint(),
        ])->validate();
    }
}
