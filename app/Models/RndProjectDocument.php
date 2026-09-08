<?php

namespace App\Models;

use Database\Factories\RndProjectDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RndProjectDocument extends Model
{
    /** @use HasFactory<RndProjectDocumentFactory> */
    use HasFactory;

    protected $fillable = ['rnd_project_id', 'name', 'file_path', 'original_name', 'mime_type', 'file_size', 'created_by'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'rnd_project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function downloadUrl(): string
    {
        try {
            return Storage::disk('b2')->temporaryUrl($this->file_path, now()->addMinutes(15), [
                'ResponseContentDisposition' => 'attachment; filename="'.$this->safeDownloadName().'"',
                'ResponseContentType' => $this->mime_type ?: 'application/octet-stream',
            ]);
        } catch (Throwable) {
            return Storage::disk('b2')->url($this->file_path);
        }
    }

    private function safeDownloadName(): string
    {
        return str_replace(['"', "\r", "\n"], '', basename($this->original_name));
    }
}
