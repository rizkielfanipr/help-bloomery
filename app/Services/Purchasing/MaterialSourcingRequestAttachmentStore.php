<?php

namespace App\Services\Purchasing;

use App\Models\MaterialSourcingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Private attachments of a Request Sourcing (PRD §29): stored on the private disk under the request
 * ID, described by metadata on the request, and never exposed by path or signed URL.
 */
class MaterialSourcingRequestAttachmentStore
{
    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, name: string, mime: string, size: int}>
     */
    public function store(MaterialSourcingRequest $request, array $files): array
    {
        $stored = [];

        try {
            foreach ($files as $file) {
                $extension = Str::lower($file->getClientOriginalExtension() ?: (string) $file->extension());
                $path = $file->storeAs(
                    'purchasing/material-sourcing-requests/'.$request->getKey(),
                    Str::uuid().'.'.$extension,
                    MaterialSourcingRequest::ATTACHMENT_DISK,
                );

                if (! is_string($path) || $path === '') {
                    throw new \RuntimeException('Lampiran gagal disimpan.');
                }

                $stored[] = [
                    'path' => $path,
                    'name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                    'mime' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                ];
            }
        } catch (Throwable $exception) {
            $this->delete(array_column($stored, 'path'));

            throw $exception;
        }

        return $stored;
    }

    /** @param list<string> $paths */
    public function delete(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk(MaterialSourcingRequest::ATTACHMENT_DISK)->delete($path);
            } catch (Throwable $exception) {
                Log::warning('Material sourcing request attachment cleanup failed.', ['exception' => $exception::class]);
            }
        }
    }
}
