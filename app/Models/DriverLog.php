<?php

namespace App\Models;

use Database\Factories\DriverLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverLog extends Model
{
    /** @use HasFactory<DriverLogFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'log_date',
        'odometer_start',
        'odometer_end',
        'notes',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'approved_at' => 'datetime',
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

    public function deliveryPoints(): HasMany
    {
        return $this->hasMany(DeliveryPoint::class)->orderBy('urutan');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
