<?php

namespace App\Models;

use Database\Factories\MarketingDesignFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Metadata of one private design object (PRD §14.3, §18). Replacing a file only hides the old
 * record and keeps its object; deleting the item removes both permanently.
 */
class MarketingDesignFile extends Model
{
    /** @use HasFactory<MarketingDesignFileFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'marketing_design_item_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'uploaded_by',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** Images and PDFs may open inline; ZIP files are only downloaded. */
    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->mime_type === 'application/pdf';
    }

    public function safeDownloadName(): string
    {
        $name = basename(str_replace('\\', '/', $this->original_name));
        $safeName = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '-', Str::ascii($name)), ' .-');

        return $safeName !== '' ? $safeName : 'design-file-'.$this->id.'.'.$this->extension;
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size_bytes, precision: 1);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketingDesignItem::class, 'marketing_design_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
