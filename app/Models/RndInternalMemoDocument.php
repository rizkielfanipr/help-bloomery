<?php

namespace App\Models;

use Database\Factories\RndInternalMemoDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One generated PDF file per revision (docs/rnd-internal-memo-prd.md §12.4, §16). A revision
 * never overwrites a previous file.
 */
class RndInternalMemoDocument extends Model
{
    /** @use HasFactory<RndInternalMemoDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_internal_memo_id',
        'revision',
        'disk',
        'file_path',
        'file_size',
        'checksum',
        'generated_by',
        'generated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'file_size' => 'integer',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(RndInternalMemo::class, 'rnd_internal_memo_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
