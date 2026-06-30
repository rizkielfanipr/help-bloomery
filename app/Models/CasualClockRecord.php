<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CasualClockRecord extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'date',
        'clock_in_at',
        'clock_in_photo',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_at',
        'clock_out_photo',
        'clock_out_lat',
        'clock_out_lng',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'clock_in_lat' => 'float',
            'clock_in_lng' => 'float',
            'clock_out_lat' => 'float',
            'clock_out_lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function overtimeRequest(): HasOne
    {
        return $this->hasOne(CasualOvertimeRequest::class);
    }

    public function effectivePosition(): ?CasualPosition
    {
        if ($this->user?->casual_position_id) {
            return $this->user->casualPosition;
        }

        $registration = CasualPositionRegistration::where('user_id', $this->user_id)
            ->whereHas('opening', fn ($q) => $q->where('work_date', $this->date->toDateString()))
            ->with('opening.casualPosition')
            ->first();

        return $registration?->opening?->casualPosition;
    }

    public function workDurationMinutes(): ?int
    {
        if (! $this->clock_in_at || ! $this->clock_out_at) {
            return null;
        }

        return (int) $this->clock_in_at->diffInMinutes($this->clock_out_at);
    }

    public function formattedWorkDuration(): ?string
    {
        $mins = $this->workDurationMinutes();
        if ($mins === null) {
            return null;
        }

        $h = intdiv($mins, 60);
        $m = $mins % 60;

        return $h > 0 ? "{$h}j {$m}m" : "{$m}m";
    }

    public function isClockedIn(): bool
    {
        return $this->clock_in_at !== null;
    }

    public function isClockedOut(): bool
    {
        return $this->clock_out_at !== null;
    }
}
