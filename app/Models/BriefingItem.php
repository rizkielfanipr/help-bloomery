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
        'reopened_at', 'reopened_by', 'reopen_reason',
    ];

    protected $casts = [
        'task_key' => 'string',
        'photo_paths' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'review_status' => BriefingReviewStatus::class,
        'reopened_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(BriefingRecord::class, 'briefing_record_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function briefingTask(): BelongsTo
    {
        return $this->belongsTo(BriefingTask::class, 'task_key', 'key');
    }

    /**
     * docs/products/daily-briefing/remediation/prd.md §12.3: a system (review-SLA/deadline) rejection has
     * no reviewer, unlike a manual rejection which always records one.
     */
    public function isSystemRejected(): bool
    {
        return $this->review_status === BriefingReviewStatus::Rejected && $this->reviewed_by === null;
    }

    /**
     * §11.2/§14: an open, unconsumed reopen grant lets a rejected item be edited again even past
     * its deadline. Consumed by BriefingSubmissionAction on the next successful save.
     */
    public function hasOpenReopenGrant(): bool
    {
        return $this->review_status === BriefingReviewStatus::Rejected && $this->reopened_at !== null;
    }
}
