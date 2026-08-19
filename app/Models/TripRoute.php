<?php

namespace App\Models;

use Database\Factories\TripRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TripRoute extends Model
{
    /** @use HasFactory<TripRouteFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'meal_allowance_amount',
        'requires_waypoint_attachment',
        'is_custom',
        'created_by_driver_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'meal_allowance_amount' => 'decimal:2',
            'requires_waypoint_attachment' => 'boolean',
            'is_custom' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function waypoints(): HasMany
    {
        return $this->hasMany(TripRouteWaypoint::class)->orderBy('urutan');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function createdByDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_driver_id');
    }
}
