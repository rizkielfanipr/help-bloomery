<?php

namespace App\Models;

use App\Enums\BriefingBundleStatus;
use App\Enums\BriefingTransitionSource;
use Database\Factories\BriefingRecordTransitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/daily-briefing-bundle-approval-prd.md §10.3: append-only audit row for one bundle status change.
 */
class BriefingRecordTransition extends Model
{
    /** @use HasFactory<BriefingRecordTransitionFactory> */
    use HasFactory;

    protected $fillable = [
        'briefing_record_id',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => BriefingBundleStatus::class,
            'to_status' => BriefingBundleStatus::class,
            'source' => BriefingTransitionSource::class,
            'metadata' => 'array',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BriefingRecord::class, 'briefing_record_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
