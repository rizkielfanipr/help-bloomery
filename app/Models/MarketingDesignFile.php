<?php

namespace App\Models;

use App\Enums\MarketingDesignFileRole;
use Database\Factories\MarketingDesignFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use LogicException;

/**
 * Metadata of one private design object (PRD §14.4, §18). Removing a file from a draft only hides
 * the record: the object stays because older versions may reference the same path.
 */
class MarketingDesignFile extends Model
{
    /** @use HasFactory<MarketingDesignFileFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'marketing_design_item_id',
        'reused_from_file_id',
        'role',
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
            'role' => MarketingDesignFileRole::class,
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $guard = function (self $file): void {
            if ($file->item()->withTrashed()->first()?->version()->first()?->status->isImmutable()) {
                throw new LogicException('File pada version published atau superseded tidak dapat diubah.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
        static::forceDeleting(fn (): never => throw new LogicException('File Master Design tidak dapat dihapus permanen.'));
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
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

    public function reusedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reused_from_file_id')->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
