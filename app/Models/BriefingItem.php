<?php

namespace App\Models;

use App\Enums\BriefingReviewStatus;
use Database\Factories\BriefingItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriefingItem extends Model
{
    /** @use HasFactory<BriefingItemFactory> */
    use HasFactory;

    protected $fillable = [
        'briefing_record_id', 'task_key', 'photo_paths',
        'notes', 'is_completed', 'completed_at',
        'submitted_by', 'submitted_at',
        'reviewed_by', 'reviewed_at',
        'review_status', 'rejection_reason',
        'requires_correction', 'correction_note',
        'reopened_at', 'reopened_by', 'reopen_reason',
    ];

    protected $attributes = [
        'requires_correction' => false,
    ];

    protected $casts = [
        'task_key' => 'string',
        'photo_paths' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'review_status' => BriefingReviewStatus::class,
        'requires_correction' => 'boolean',
        'reopened_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(BriefingRecord::class, 'briefing_record_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
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
     * docs/daily-briefing-remediation-prd.md §12.3: a system (review-SLA/deadline) rejection has
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
    /**
     * Real per-item submissions still waiting past the review SLA, limited to legacy records that
     * have not been grouped into bundles; bundle items are only ever decided at bundle level.
     */
    public function scopeLegacyAwaitingReviewPastSla(Builder $query, int $days): void
    {
        $query->whereIn('review_status', [BriefingReviewStatus::LegacyPending->value, BriefingReviewStatus::SupervisorReview->value])
            ->where('is_completed', true)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', now()->subDays($days))
            ->whereHas('record', fn (Builder $record): Builder => $record->whereNull('status'));
    }

    public function hasOpenReopenGrant(): bool
    {
        return $this->review_status === BriefingReviewStatus::Rejected && $this->reopened_at !== null;
    }

    /**
     * Whether staff actually put something in: a tick, a photo or a note. A row the system created
     * without content (an empty slot, or a deadline rejection) is not a submission.
     */
    public function hasSubmittedContent(): bool
    {
        return (bool) $this->is_completed || ! empty($this->photo_paths) || filled($this->notes);
    }
}
