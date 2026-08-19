<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripRouteWaypoint extends Model
{
    protected $fillable = [
        'trip_route_id',
        'urutan',
        'name',
        'description',
        'latitude',
        'longitude',
        'radius_meters',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
        ];
    }

    public function tripRoute(): BelongsTo
    {
        return $this->belongsTo(TripRoute::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(TripWaypointCheckin::class);
    }
}
