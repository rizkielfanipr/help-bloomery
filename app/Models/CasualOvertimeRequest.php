<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasualOvertimeRequest extends Model
{
    protected $fillable = [
        'casual_clock_record_id',
        'user_id',
        'requested_hours',
        'reason',
        'status',
        'approved_hours',
        'overtime_fee',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_hours' => 'float',
            'approved_hours' => 'float',
            'overtime_fee' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function clockRecord(): BelongsTo
    {
        return $this->belongsTo(CasualClockRecord::class, 'casual_clock_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the effective position, falling back to the registration for the clock record's date
     * when the user's direct casual_position_id is not set.
     */
    public function effectivePosition(): ?CasualPosition
    {
        // Direct position on user
        if ($this->user?->casual_position_id) {
            return $this->user->casualPosition;
        }

        // Fall back to registration for the clock record's date
        $clockRecord = $this->clockRecord;
        if (! $clockRecord) {
            return null;
        }

        $registration = CasualPositionRegistration::where('user_id', $clockRecord->user_id)
            ->whereHas('opening', fn ($q) => $q->where('work_date', $clockRecord->date->toDateString()))
            ->with('opening.casualPosition')
            ->first();

        return $registration?->opening?->casualPosition;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
