<?php

namespace App\Services\BrandMarketing\DesignMaster;

use App\Models\MarketingDesignBundle;
use App\Models\MarketingDesignFile;
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
 * written for a record that could not be saved, and `purge()` removes the objects of a
 * permanently deleted item. No other code path deletes objects.
 */
class MarketingDesignFileStore
{
    public const DISK = 'b2';

    /** Matches the global Livewire temporary upload rule; raising it needs the infrastructure audit in PRD §18.2. */
    public const MAX_KILOBYTES = 12288;

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'zip'];

    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
    ];

    private const TEMPORARY_URL_MINUTES = 15;

    /**
     * Browsers on Windows report ZIP files as application/x-zip-compressed, so the upload field also accepts it;
     * the server still validates the MIME detected from the content.
     *
     * @return list<string>
     */
    public static function acceptedBrowserTypes(): array
    {
        return [...array_keys(self::EXTENSION_BY_MIME), 'application/x-zip-compressed'];
    }

    public static function formatHint(): string
    {
        return 'Format '.strtoupper(implode(', ', self::EXTENSIONS)).'. Maksimal '.(self::MAX_KILOBYTES / 1024).' MB.';
    }

    /**
     * @return array{disk: string, path: string, original_name: string, mime_type: string, extension: string, size_bytes: int, checksum_sha256: string}
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file, MarketingDesignBundle $bundle): array
    {
        $this->validate($file);

        $mimeType = (string) $file->getMimeType();
        $extension = self::EXTENSION_BY_MIME[$mimeType];
        $checksum = hash_file('sha256', (string) $file->getRealPath());
        $directory = sprintf('brand-marketing/design-master/brand-%d/bundle-%d', $bundle->brand_id, $bundle->id);

        try {
            $path = Storage::disk(self::DISK)->putFileAs($directory, $file, Str::uuid().'.'.$extension);
        } catch (Throwable $exception) {
            Log::warning('Master Design upload failed', ['bundle_id' => $bundle->id, 'exception' => $exception::class]);
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

    /**
     * Removes the objects of permanently deleted records. A failure is logged and leaves an orphan
     * object rather than undoing the deletion.
     *
     * @param  list<array{disk: string, path: string}>  $files
     */
    public function purge(array $files): void
    {
        foreach ($files as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $exception) {
                Log::warning('Master Design object purge failed', ['exception' => $exception::class]);
            }
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

    /** @throws ValidationException */
    private function validate(UploadedFile $file): void
    {
        Validator::make(['file' => $file], [
            'file' => [
                'required',
                'file',
                'extensions:'.implode(',', self::EXTENSIONS),
                'mimetypes:'.implode(',', array_keys(self::EXTENSION_BY_MIME)),
                'max:'.self::MAX_KILOBYTES,
            ],
        ], [
            'file.extensions' => 'File ditolak. '.self::formatHint(),
            'file.mimetypes' => 'File ditolak. '.self::formatHint(),
            'file.max' => 'Ukuran file melebihi batas. '.self::formatHint(),
        ])->validate();
    }
}
