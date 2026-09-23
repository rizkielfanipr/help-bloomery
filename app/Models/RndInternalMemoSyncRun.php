<?php

namespace App\Models;

use App\Enums\RndInternalMemoSyncRunStatus;
use Database\Factories\RndInternalMemoSyncRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One BOM synchronization attempt across every Menu of a memo (docs/rnd-internal-memo-prd.md
 * §12.5, §17).
 */
class RndInternalMemoSyncRun extends Model
{
    /** @use HasFactory<RndInternalMemoSyncRunFactory> */
    use HasFactory;

    protected $fillable = [
        'rnd_internal_memo_id',
        'company_code',
        'status',
        'started_at',
        'finished_at',
        'request_count',
        'error_count',
        'error_summary',
        'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RndInternalMemoSyncRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'request_count' => 'integer',
            'error_count' => 'integer',
        ];
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(RndInternalMemo::class, 'rnd_internal_memo_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
