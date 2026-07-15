<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'name',
        'esb_branch_code',
        'esb_comcode',
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

    public function getEsbTokenAttribute(): string
    {
        return config('esb.tokens.'.$this->esb_comcode, '');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branches');
    }

    public function openings(): HasMany
    {
        return $this->hasMany(CasualPositionOpening::class);
    }

    public function clockRecords(): HasMany
    {
        return $this->hasMany(CasualClockRecord::class);
    }

    public function briefingTasks(): HasMany
    {
        return $this->hasMany(BriefingTask::class);
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
