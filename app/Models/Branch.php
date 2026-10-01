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
        'stock_card_esb_code_id',
        'location_required',
        'sales_shift_count',
        'sales_assessment_started_at',
        'sales_assessment_excluded_dates',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'is_active' => 'boolean',
            'location_required' => 'boolean',
            'sales_shift_count' => 'integer',
            'shifts_changed_at' => 'datetime',
            'sales_assessment_started_at' => 'immutable_date',
            'sales_assessment_excluded_dates' => 'array',
        ];
    }

    public function esbCodes(): HasMany
    {
        return $this->hasMany(BranchEsbCode::class);
    }

    public function stockCardEsbCode(): BelongsTo
    {
        return $this->belongsTo(BranchEsbCode::class, 'stock_card_esb_code_id');
    }

    public function activeStockCardEsbCode(): ?BranchEsbCode
    {
        $mapping = $this->stockCardEsbCode;

        return $mapping?->branch_id === $this->id && $mapping->is_active ? $mapping : null;
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

    public function qualityControlAudits(): HasMany
    {
        return $this->hasMany(QualityControlAudit::class);
    }

    public function salesReports(): HasMany
    {
        return $this->hasMany(SalesReport::class);
    }

    public function salesProjectionTargets(): BelongsToMany
    {
        return $this->belongsToMany(
            RndProductSalesProjection::class,
            'rnd_product_sales_projection_branch_targets',
            'branch_id',
            'rnd_product_sales_projection_id',
        )->withPivot('target_quantity')->withTimestamps();
    }

    public function salesShifts(): HasMany
    {
        return $this->hasMany(BranchSalesShift::class)->orderBy('shift_number');
    }

    public function activeSalesShifts(): HasMany
    {
        return $this->hasMany(BranchSalesShift::class)
            ->where('is_active', true)
            ->orderBy('shift_number');
    }

    public function configuredSalesShift(int $shiftNumber): ?BranchSalesShift
    {
        return $this->activeSalesShifts->firstWhere('shift_number', $shiftNumber);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Start and end time of a shift as configured on the branch, with the defaults for branches without shifts.
     *
     * @return array{0: string, 1: string}
     */
    public function salesShiftWindow(int $shiftNumber): array
    {
        $shift = $this->configuredSalesShift($shiftNumber);

        if ($shift) {
            return [$shift->start_time, $shift->end_time];
        }

        return match ($shiftNumber) {
            1 => ['07:00:00', '15:00:00'],
            2 => ['15:00:00', '23:00:00'],
            default => ['00:00:00', '23:59:59'],
        };
    }

    public function hasSalesShift(int $shiftNumber): bool
    {
        return $this->activeSalesShifts->isNotEmpty()
            ? $this->activeSalesShifts->contains('shift_number', $shiftNumber)
            : $shiftNumber >= 1 && $shiftNumber <= $this->sales_shift_count;
    }

    /** @return array<int, int> */
    public function salesShiftNumbers(): array
    {
        return $this->activeSalesShifts->isNotEmpty()
            ? $this->activeSalesShifts->pluck('shift_number')->all()
            : range(1, max(1, $this->sales_shift_count));
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
