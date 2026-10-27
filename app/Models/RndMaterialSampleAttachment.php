<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Private sample photo (§23). Served only through the authorized attachment controller.
 */
class RndMaterialSampleAttachment extends Model
{
    public const KIND_HANDOVER = 'handover';

    public const KIND_RECEIPT = 'receipt';

    public const UPDATED_AT = null;

    protected $fillable = [
        'rnd_material_sample_cycle_id',
        'kind',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public static function disk(): string
    {
        return (string) config('filesystems.rnd_material_sample_disk', 'b2');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(RndMaterialSampleCycle::class, 'rnd_material_sample_cycle_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
