<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripWaypointCheckin extends Model
{
    protected $fillable = [
        'trip_id',
        'trip_route_waypoint_id',
        'checked_in_at',
        'attachment_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function waypoint(): BelongsTo
    {
        return $this->belongsTo(TripRouteWaypoint::class, 'trip_route_waypoint_id');
    }
}
