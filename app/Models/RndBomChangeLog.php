<?php

namespace App\Models;

use App\Enums\RndBomChangeLogEvent;
use App\Enums\RndBomChangeLogSource;
use App\Enums\RndBomChangeLogStatus;
use Database\Factories\RndBomChangeLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of one BOM mutation attempt — before/requested/after snapshots, diff, actor, and
 * reconciliation outcome (docs/rnd-bom-adjustment-prd.md §15). Records are never deleted when
 * the underlying BOM becomes inactive.
 */
class RndBomChangeLog extends Model
{
    /** @use HasFactory<RndBomChangeLogFactory> */
    use HasFactory;

    protected $fillable = [
        'esb_bom_id',
        'bom_code',
        'bom_name',
        'product_code',
        'product_name',
        'source',
        'event',
        'status',
        'reason',
        'before_snapshot',
        'requested_snapshot',
        'after_snapshot',
        'changes',
        'error_code',
        'error_message',
        'esb_edited_at_before',
        'esb_edited_at_after',
        'changed_by',
        'reconciled_by',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'esb_bom_id' => 'integer',
            'source' => RndBomChangeLogSource::class,
            'event' => RndBomChangeLogEvent::class,
            'status' => RndBomChangeLogStatus::class,
            'before_snapshot' => 'array',
            'requested_snapshot' => 'array',
            'after_snapshot' => 'array',
            'changes' => 'array',
            'reconciled_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
