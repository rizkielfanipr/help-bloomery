<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasualClockRecord extends Model
{
    protected $fillable = [
        'user_id',
        'shift_id',
        'date',
        'clock_in_at',
        'clock_in_photo',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_at',
        'clock_out_photo',
        'clock_out_lat',
        'clock_out_lng',
        'is_late',
        'late_minutes',
        'is_early_out',
        'early_out_minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'is_late' => 'boolean',
            'is_early_out' => 'boolean',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CasualShift::class, 'shift_id');
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
