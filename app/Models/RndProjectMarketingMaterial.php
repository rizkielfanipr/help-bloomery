<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RndProjectMarketingMaterial extends Model
{
    public const TYPES = [
        'packaging_design' => 'Design Packaging',
        'sticker' => 'Sticker',
        'product_photo' => 'Foto Produk',
        'social_media' => 'Social Media',
        'catalogue' => 'Katalog',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'rnd_project_product_id',
        'type',
        'title',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'notes',
        'created_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(RndProjectProduct::class, 'rnd_project_product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fileUrl(): string
    {
        try {
            return Storage::disk('b2')->temporaryUrl($this->file_path, now()->addHour());
        } catch (Throwable) {
            return Storage::disk('b2')->url($this->file_path);
        }
    }

    public function downloadUrl(): string
    {
        try {
            return Storage::disk('b2')->temporaryUrl(
                $this->file_path,
                now()->addMinutes(15),
                [
                    'ResponseContentDisposition' => 'attachment; filename="'.$this->safeDownloadName().'"',
                    'ResponseContentType' => $this->mime_type ?: 'application/octet-stream',
                ],
            );
        } catch (Throwable) {
            return Storage::disk('b2')->url($this->file_path);
        }
    }

    private function safeDownloadName(): string
    {
        return str_replace(['"', "\r", "\n"], '', basename($this->original_name));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
