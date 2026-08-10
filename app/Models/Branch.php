<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'name',
        'address',
        'lat',
        'lng',
        'radius_meters',
        'is_active',
        'location_required',
        'sales_shift_count',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'is_active' => 'boolean',
            'location_required' => 'boolean',
            'sales_shift_count' => 'integer',
        ];
    }

    public function esbCodes(): HasMany
    {
        return $this->hasMany(BranchEsbCode::class);
    }

    /** @return Collection<int, BranchEsbCode> */
    public function activeEsbCodes()
    {
        return $this->esbCodes->where('is_active', true)->values();
    }

    public function hasEsbIntegration(): bool
    {
        return $this->activeEsbCodes()->isNotEmpty();
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

    public function salesReports(): HasMany
    {
        return $this->hasMany(SalesReport::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function hasSalesShift(int $shiftNumber): bool
    {
        return $shiftNumber >= 1 && $shiftNumber <= $this->sales_shift_count;
    }

    /** @return array<int, int> */
    public function salesShiftNumbers(): array
    {
        return range(1, max(1, $this->sales_shift_count));
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
