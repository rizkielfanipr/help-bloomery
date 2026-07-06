<?php

namespace App\Models;

use App\Enums\TripStatus;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'driver_id',
        'vehicle_id',
        'trip_route_id',
        'trip_date',
        'status',
        'started_at',
        'completed_at',
        'has_fuel_fillup',
        'meal_allowance_amount',
        'notes',
        'odo_start',
        'odo_start_photo',
        'odo_end',
        'odo_end_photo',
    ];

    protected static function booted(): void
    {
        static::creating(function (Trip $trip) {
            if (empty($trip->code)) {
                do {
                    $code = 'DV-'.str_pad(random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
                } while (static::where('code', $code)->exists());

                $trip->code = $code;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'status' => TripStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'has_fuel_fillup' => 'boolean',
            'meal_allowance_amount' => 'decimal:2',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function tripRoute(): BelongsTo
    {
        return $this->belongsTo(TripRoute::class);
    }

    public function waypointCheckins(): HasMany
    {
        return $this->hasMany(TripWaypointCheckin::class);
    }

    public function fuelFillup(): HasOne
    {
        return $this->hasOne(TripFuelFillup::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === TripStatus::Completed;
    }

    public function isInProgress(): bool
    {
        return $this->status === TripStatus::InProgress;
    }

    /**
     * Get checkin for a specific waypoint.
     */
    public function getCheckinForWaypoint(int $waypointId): ?TripWaypointCheckin
    {
        return $this->waypointCheckins->firstWhere('trip_route_waypoint_id', $waypointId);
    }

    /**
     * Check if all waypoints have been checked in (and attachment uploaded if required).
     */
    public function allWaypointsCompleted(): bool
    {
        $requiresAttachment = $this->tripRoute->requires_waypoint_attachment;
        $waypointIds = $this->tripRoute->waypoints->pluck('id');

        foreach ($waypointIds as $waypointId) {
            $checkin = $this->getCheckinForWaypoint($waypointId);

            if (! $checkin || ! $checkin->checked_in_at) {
                return false;
            }

            if ($requiresAttachment && ! $checkin->attachment_path) {
                return false;
            }
        }

        return true;
    }
}
