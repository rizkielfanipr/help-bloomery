<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'lat',
        'lng',
        'radius_meters',
        'is_active',
        'location_required',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'is_active' => 'boolean',
            'location_required' => 'boolean',
        ];
    }

    public function openings(): HasMany
    {
        return $this->hasMany(CasualPositionOpening::class);
    }

    public function clockRecords(): HasMany
    {
        return $this->hasMany(CasualClockRecord::class);
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
