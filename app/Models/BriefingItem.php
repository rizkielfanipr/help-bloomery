<?php

namespace App\Models;

use App\Enums\BriefingReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefingItem extends Model
{
    protected $fillable = [
        'briefing_record_id', 'task_key', 'photo_paths',
        'notes', 'is_completed', 'completed_at',
        'reviewed_by', 'reviewed_at',
        'review_status', 'rejection_reason',
    ];

    protected $casts = [
        'task_key' => 'string',
        'photo_paths' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'review_status' => BriefingReviewStatus::class,
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(BriefingRecord::class, 'briefing_record_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function briefingTask(): BelongsTo
    {
        return $this->belongsTo(BriefingTask::class, 'task_key', 'key');
    }
}
