<?php

namespace App\Services\Rnd\Material;

use App\Models\RndMaterialSampleAttachment;
use App\Models\RndMaterialSampleCycle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores private sample photos (§23): MIME detected from content, size/count validated server-side,
 * files written under a per-cycle path, and metadata recorded. `discard()` removes files written
 * for a step whose database transaction failed, so no orphan file is left behind.
 */
class MaterialSampleEvidenceStore
{
    public const MAX_FILES = 5;

    public const MAX_KILOBYTES = 5120;

    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<array{path: string, original_name: string, mime_type: string, size: int}>
     *
     * @throws ValidationException
     */
    public function store(array $files, int $materialId, string $kind, string $field): array
    {
        $validator = Validator::make([$field => array_values($files)], [
            $field => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            "{$field}.*" => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_KILOBYTES],
        ], [
            "{$field}.required" => 'Foto wajib diunggah.',
            "{$field}.min" => 'Foto wajib diunggah.',
            "{$field}.*.mimetypes" => 'Foto harus JPG, PNG, atau WEBP.',
            "{$field}.*.max" => 'Ukuran foto maksimal 5 MB.',
        ]);
        $validator->validate();

        $disk = RndMaterialSampleAttachment::disk();
        $stored = [];
        foreach (array_values($files) as $file) {
            $extension = match ($file->getMimeType()) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $path = "rnd/materials/{$materialId}/samples/{$kind}/".Str::uuid().'.'.$extension;
            Storage::disk($disk)->put($path, (string) file_get_contents($file->getRealPath()));
            $stored[] = [
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @param  list<array{path: string, original_name: string, mime_type: string, size: int}>  $stored
     */
    public function attach(RndMaterialSampleCycle $cycle, array $stored, string $kind, User $uploader): void
    {
        foreach ($stored as $file) {
            $cycle->attachments()->create($file + ['kind' => $kind, 'disk' => RndMaterialSampleAttachment::disk(), 'uploaded_by' => $uploader->id]);
        }
    }

    /**
     * @param  list<array{path: string}>  $stored
     */
    public function discard(array $stored): void
    {
        if ($stored !== []) {
            Storage::disk(RndMaterialSampleAttachment::disk())->delete(array_column($stored, 'path'));
        }
    }
}
