<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CasualShift extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'tolerance_late_minutes',
        'tolerance_early_out_minutes',
        'location_required',
        'location_lat',
        'location_lng',
        'location_radius_meters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'location_required' => 'boolean',
            'is_active' => 'boolean',
            'location_lat' => 'float',
            'location_lng' => 'float',
        ];
    }

    public function clockRecords(): HasMany
    {
        return $this->hasMany(CasualClockRecord::class, 'shift_id');
    }

    /**
     * Calculate how many minutes late a given timestamp is from the shift start.
     * Returns 0 if not late (within tolerance).
     */
    public function calculateLateMinutes(Carbon $clockInAt): int
    {
        $shiftStart = Carbon::parse($clockInAt->toDateString().' '.$this->start_time);
        $toleranceDeadline = $shiftStart->copy()->addMinutes($this->tolerance_late_minutes);

        if ($clockInAt->lte($toleranceDeadline)) {
            return 0;
        }

        return (int) $shiftStart->diffInMinutes($clockInAt);
    }

    /**
     * Calculate how many minutes early a given timestamp is from the shift end.
     * Returns 0 if not early (within tolerance).
     */
    public function calculateEarlyOutMinutes(Carbon $clockOutAt): int
    {
        $shiftEnd = Carbon::parse($clockOutAt->toDateString().' '.$this->end_time);
        $toleranceEarliest = $shiftEnd->copy()->subMinutes($this->tolerance_early_out_minutes);

        if ($clockOutAt->gte($toleranceEarliest)) {
            return 0;
        }

        return (int) $clockOutAt->diffInMinutes($shiftEnd);
    }
}
