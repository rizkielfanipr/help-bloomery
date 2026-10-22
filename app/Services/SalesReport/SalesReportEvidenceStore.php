<?php

namespace App\Services\SalesReport;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Private evidence of Compliment and Void (docs/sales-report-compliment-void-remediation-prd.md
 * §9.4, §10.5): new uploads are images only, stored on the private disk, and only ever opened
 * through a short temporary URL created after authorization. Legacy Compliment PDFs stay
 * readable; they are just no longer accepted for new submissions.
 */
class SalesReportEvidenceStore
{
    /** @var list<string> */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_KILOBYTES = 5120;

    public const MAX_COMPLIMENT_FILES = 5;

    public const COMPLIMENT_DIRECTORY = 'sales-report-compliments';

    public const VOID_DIRECTORY = 'sales-report-voids';

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('filesystems.sales_report_evidence_disk', 'b2'));
    }

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, ['disk' => (string) config('filesystems.sales_report_evidence_disk', 'b2')]);
    }

    /**
     * Best-effort cleanup; a failure is logged without the path and never breaks the caller.
     *
     * @param  array<int, string|null>  $paths
     */
    public function delete(array $paths): void
    {
        $paths = array_values(array_filter($paths, fn (?string $path): bool => filled($path)));
        if ($paths === []) {
            return;
        }

        try {
            $this->disk()->delete($paths);
        } catch (Throwable $exception) {
            Log::warning('Sales Report evidence cleanup failed.', ['count' => count($paths), 'exception' => $exception::class]);
        }
    }

    public function temporaryUrl(string $path): string
    {
        return $this->disk()->temporaryUrl($path, now()->addMinutes(10));
    }

    public static function isLegacyPdf(string $path): bool
    {
        return str_ends_with(mb_strtolower($path), '.pdf');
    }

    /**
     * Validation rules for one new evidence image.
     *
     * @return list<string>
     */
    public static function imageRules(): array
    {
        return ['file', 'mimes:'.implode(',', self::IMAGE_EXTENSIONS), 'max:'.self::MAX_KILOBYTES];
    }
}
